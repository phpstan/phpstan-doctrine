<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeContext;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\DependencyInjection\Container;
use PHPStan\Parser\Parser;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Generic\TemplateTypeMap;
use function count;
use function is_array;

class OtherMethodQueryBuilderParser
{

	/** @var bool */
	private $descendIntoOtherMethods;

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var Parser */
	private $parser;

	/** @var Container */
	private $container;

	public function __construct(bool $descendIntoOtherMethods, ReflectionProvider $reflectionProvider, Parser $parser, Container $container)
	{
		$this->descendIntoOtherMethods = $descendIntoOtherMethods;
		$this->reflectionProvider = $reflectionProvider;
		$this->parser = $parser;
		$this->container = $container;
	}

	/**
	 * @return QueryBuilderType[]
	 */
	public function getQueryBuilderTypes(Scope $scope, MethodCall $methodCall): array
	{
		if (!$this->descendIntoOtherMethods || !$methodCall->var instanceof MethodCall) {
			return [];
		}

		return $this->findQueryBuilderTypesInCalledMethod($scope, $methodCall->var);
	}
	/**
	 * @return QueryBuilderType[]
	 */
	private function findQueryBuilderTypesInCalledMethod(Scope $scope, MethodCall $methodCall): array
	{
		$methodCalledOnType = $scope->getType($methodCall->var);
		if (!$methodCall->name instanceof Identifier) {
			return [];
		}

		$methodCalledOnTypeClassNames = $methodCalledOnType->getObjectClassNames();

		if (count($methodCalledOnTypeClassNames) !== 1) {
			return [];
		}

		if (!$this->reflectionProvider->hasClass($methodCalledOnTypeClassNames[0])) {
			return [];
		}

		$classReflection = $this->reflectionProvider->getClass($methodCalledOnTypeClassNames[0]);
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

		$nodeScopeResolver = $this->container->getByType(NodeScopeResolver::class);
		$scopeFactory = $this->container->getByType(ScopeFactory::class);

		$methodScope = $scopeFactory->create(ScopeContext::create($fileName));
		if ($scope->getNamespace() !== null) {
			$methodScope = $methodScope->enterNamespace($scope->getNamespace());
		}

		$methodScope = $methodScope->enterClass($methodReflection->getDeclaringClass())
			->enterClassMethod($methodNode, TemplateTypeMap::createEmpty(), [], null, null, null, false, false, false);

		$queryBuilderTypes = [];

		$nodeScopeResolver->processNodes($methodNode->stmts, $methodScope, static function (Node $node, Scope $scope) use (&$queryBuilderTypes): void {
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
	 * @param Node[] $nodes
	 */
	private function findClassNode(string $className, array $nodes): ?Class_
	{
		foreach ($nodes as $node) {
			if (
				$node instanceof Class_
				&& $node->namespacedName !== null
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
	 * @param Stmt[] $classStatements
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
