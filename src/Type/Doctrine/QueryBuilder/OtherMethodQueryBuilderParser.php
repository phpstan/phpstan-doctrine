<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node;
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
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\UnionType;
use function array_key_exists;
use function is_array;
use function sprintf;

class OtherMethodQueryBuilderParser
{

	private Parser $parser;

	private Container $container;

	/**
	 * Null if the method is currently being processed
	 *
	 * @var array<string, list<QueryBuilderType>|null>
	 */
	private array $cache = [];

	public function __construct(Parser $parser, Container $container)
	{
		$this->parser = $parser;
		$this->container = $container;
	}

	/**
	 * @return list<QueryBuilderType>
	 */
	public function findQueryBuilderTypesInCalledMethod(Scope $scope, MethodReflection $methodReflection): array
	{
		$methodName = $methodReflection->getName();
		$className = $methodReflection->getDeclaringClass()->getName();
		$fileName = $methodReflection->getDeclaringClass()->getFileName();
		if ($fileName === null) {
			return [];
		}

		$cacheKey = $this->buildCacheKey($fileName, $className, $methodName);

		if (array_key_exists($cacheKey, $this->cache)) {
			if ($this->cache[$cacheKey] === null) {
				return []; // recursion
			}

			return $this->cache[$cacheKey];
		}

		$this->cache[$cacheKey] = null;

		$nodes = $this->parser->parseFile($fileName);
		$classNode = $this->findClassNode($className, $nodes);
		if ($classNode === null) {
			return [];
		}

		$methodNode = $this->findMethodNode($methodName, $classNode->stmts);
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

			TypeTraverser::map($exprType, static function (Type $type, callable $traverse) use (&$queryBuilderTypes): Type {
				if ($type instanceof UnionType || $type instanceof IntersectionType) {
					return $traverse($type);
				}

				if ($type instanceof QueryBuilderType) {
					$queryBuilderTypes[] = $type;
				}

				return $type;
			});
		});

		$this->cache[$cacheKey] = $queryBuilderTypes;

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

	private function buildCacheKey(string $fileName, string $declaringClassName, string $methodName): string
	{
		return sprintf('%s-%s-%s', $fileName, $declaringClassName, $methodName);
	}

}
