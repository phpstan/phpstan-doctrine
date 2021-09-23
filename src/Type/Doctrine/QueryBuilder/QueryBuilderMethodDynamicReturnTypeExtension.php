<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use Doctrine\ORM\QueryBuilder;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Broker\Broker;
use PHPStan\DependencyInjection\Container;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Doctrine\DoctrineTypeUtils;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;

class QueryBuilderMethodDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension, BrokerAwareExtension
{

	private const MAX_COMBINATIONS = 16;

	/** @var \PHPStan\DependencyInjection\Container */
	private $container;

	/** @var \PHPStan\Parser\Parser */
	private $parser;

	/** @var string|null */
	private $queryBuilderClass;

	/** @var bool */
	private $descendIntoOtherMethods;

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	public function __construct(
		Container $container,
		Parser $parser,
		?string $queryBuilderClass,
		bool $descendIntoOtherMethods
	)
	{
		$this->container = $container;
		$this->parser = $parser;
		$this->queryBuilderClass = $queryBuilderClass;
		$this->descendIntoOtherMethods = $descendIntoOtherMethods;
	}

	public function setBroker(Broker $broker): void
	{
		$this->broker = $broker;
	}

	public function getClass(): string
	{
		return $this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		$returnType = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		)->getReturnType();
		if ($returnType instanceof MixedType) {
			return false;
		}
		return (new ObjectType(QueryBuilder::class))->isSuperTypeOf($returnType)->yes();
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		if (!$methodCall->name instanceof Identifier) {
			return $calledOnType;
		}
		$lowerMethodName = strtolower($methodCall->name->toString());
		if (in_array($lowerMethodName, [
			'setparameter',
			'setparameters',
		], true)) {
			return $calledOnType;
		}

		$queryBuilderTypes = DoctrineTypeUtils::getQueryBuilderTypes($calledOnType);
		if (count($queryBuilderTypes) === 0) {
			if (!$this->descendIntoOtherMethods || !$methodCall->var instanceof MethodCall) {
				return $calledOnType;
			}

			$queryBuilderTypes = $this->findQueryBuilderTypesInCalledMethod($scope, $methodCall->var);
			if (count($queryBuilderTypes) === 0) {
				return $calledOnType;
			}
		}

		if (count($queryBuilderTypes) > self::MAX_COMBINATIONS) {
			return $calledOnType;
		}

		$resultTypes = [];
		foreach ($queryBuilderTypes as $queryBuilderType) {
			$resultTypes[] = $queryBuilderType->append($methodCall);
		}

		return TypeCombinator::union(...$resultTypes);
	}

	/**
	 * @param \PHPStan\Analyser\Scope $scope
	 * @param \PhpParser\Node\Expr\MethodCall $methodCall
	 * @return \PHPStan\Type\Doctrine\QueryBuilder\QueryBuilderType[]
	 */
	private function findQueryBuilderTypesInCalledMethod(Scope $scope, MethodCall $methodCall): array
	{
		$methodCalledOnType = $scope->getType($methodCall->var);
		if (!$methodCall->name instanceof Identifier) {
			return [];
		}

		if (!$methodCalledOnType instanceof TypeWithClassName) {
			return [];
		}

		if (!$this->broker->hasClass($methodCalledOnType->getClassName())) {
			return [];
		}

		$classReflection = $this->broker->getClass($methodCalledOnType->getClassName());
		$methodName = $methodCall->name->toString();
		if (!$classReflection->hasNativeMethod($methodName)) {
			return [];
		}

		$methodReflection = $classReflection->getNativeMethod($methodName);
		$fileName = $methodReflection->getDeclaringClass()->getFileName();
		if ($fileName === null) {
			return [];
		}

		$nodes = $this->parser->parseFile($fileName);
		$classNode = $this->findClassNode($methodReflection->getDeclaringClass()->getName(), $nodes);
		if ($classNode === null) {
			return [];
		}

		$methodNode = $this->findMethodNode($methodReflection->getName(), $classNode->stmts);
		if ($methodNode === null || $methodNode->stmts === null) {
			return [];
		}

		/** @var \PHPStan\Analyser\NodeScopeResolver $nodeScopeResolver */
		$nodeScopeResolver = $this->container->getByType(NodeScopeResolver::class);

		/** @var \PHPStan\Analyser\ScopeFactory $scopeFactory */
		$scopeFactory = $this->container->getByType(ScopeFactory::class);

		$methodScope = $scopeFactory->create(
			ScopeContext::create($fileName),
			$scope->isDeclareStrictTypes(),
			[],
			$methodReflection,
			$scope->getNamespace()
		)->enterClass($methodReflection->getDeclaringClass())->enterClassMethod($methodNode, TemplateTypeMap::createEmpty(), [], null, null, null, false, false, false);

		$queryBuilderTypes = [];

		$nodeScopeResolver->processNodes($methodNode->stmts, $methodScope, function (Node $node, Scope $scope) use (&$queryBuilderTypes): void {
			if (!$node instanceof Return_ || $node->expr === null) {
				return;
			}

			$exprType = $scope->getType($node->expr);
			if (!$exprType instanceof QueryBuilderType) {
				return;
			}

			$queryBuilderTypes[] = $exprType;
		});

		return $queryBuilderTypes;
	}

	/**
	 * @param string $className
	 * @param \PhpParser\Node[] $nodes
	 * @return \PhpParser\Node\Stmt\Class_|null
	 */
	private function findClassNode(string $className, array $nodes): ?Class_
	{
		foreach ($nodes as $node) {
			if (
				$node instanceof Class_
				&& $node->namespacedName->toString() === $className
			) {
				return $node;
			}

			if (
				!$node instanceof Namespace_
				&& !$node instanceof Declare_
			) {
				continue;
			}
			$subNodeNames = $node->getSubNodeNames();
			foreach ($subNodeNames as $subNodeName) {
				$subNode = $node->{$subNodeName};
				if (!is_array($subNode)) {
					$subNode = [$subNode];
				}

				$result = $this->findClassNode($className, $subNode);
				if ($result === null) {
					continue;
				}

				return $result;
			}
		}

		return null;
	}

	/**
	 * @param string $methodName
	 * @param \PhpParser\Node\Stmt[] $classStatements
	 * @return \PhpParser\Node\Stmt\ClassMethod|null
	 */
	private function findMethodNode(string $methodName, array $classStatements): ?ClassMethod
	{
		foreach ($classStatements as $statement) {
			if (
				$statement instanceof ClassMethod
				&& $statement->name->toString() === $methodName
			) {
				return $statement;
			}
		}

		return null;
	}

}
