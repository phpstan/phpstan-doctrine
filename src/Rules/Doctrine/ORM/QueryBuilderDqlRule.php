<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\QueryBuilderType;
use function method_exists;
use function strtolower;

class QueryBuilderDqlRule implements Rule
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	/**
	 * @param \PhpParser\Node\Expr\MethodCall $node
	 * @param Scope $scope
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Node\Identifier) {
			return [];
		}

		if ($node->name->toLowerString() !== 'getquery') {
			return [];
		}

		$calledOnType = $scope->getType($node->var);
		if (!$calledOnType instanceof QueryBuilderType) {
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			throw new ShouldNotHappenException('Please provide the "objectManagerLoader" setting for the DQL validation.');
		}
		if (!$objectManager instanceof EntityManagerInterface) {
			return [];
		}

		/** @var EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$queryBuilder = $objectManager->createQueryBuilder();

		foreach ($calledOnType->getMethodCalls() as $methodCall) {
			if (!$methodCall->name instanceof Node\Identifier) {
				continue;
			}

			$methodName = $methodCall->name->toString();
			if (in_array(strtolower($methodName), [
				'setparameter',
				'setparameters',
			], true)) {
				continue;
			}

			if (!method_exists($queryBuilder, $methodName)) {
				continue;
			}

			try {
				$args = $this->processArgs($scope, $methodName, $methodCall->args);
			} catch (DynamicQueryBuilderArgumentException $e) {
				// todo parameter "detectDynamicQueryBuilders" a hlasit jako error - pro oddebugovani
				return [];
			}

			try {
				$queryBuilder->{$methodName}(...$args);
			} catch (\Throwable $e) {
				return [
					sprintf('Calling %s() on %s: %s', $methodName, get_class($queryBuilder), $e->getMessage()),
				];
			}
		}

		$query = $objectManager->createQuery($queryBuilder->getDQL());

		try {
			$query->getSQL();
		} catch (\Doctrine\ORM\Query\QueryException $e) {
			return [sprintf('QueryBuilder: %s', $e->getMessage())];
		}

		return [];
	}

	/**
	 * @param \PHPStan\Analyser\Scope $scope
	 * @param string $methodName
	 * @param \PhpParser\Node\Arg[] $methodCallArgs
	 * @return mixed[]
	 */
	protected function processArgs(Scope $scope, string $methodName, array $methodCallArgs): array
	{
		$args = [];
		foreach ($methodCallArgs as $arg) {
			$value = $scope->getType($arg->value);
			// todo $qb->expr() support
			// todo new Expr\Andx support
			if ($value instanceof ConstantArrayType) {
				$array = [];
				foreach ($value->getKeyTypes() as $i => $keyType) {
					$valueType = $value->getValueTypes()[$i];
					if (!$valueType instanceof ConstantScalarType) {
						throw new DynamicQueryBuilderArgumentException();
					}
					$array[$keyType->getValue()] = $valueType->getValue();
				}

				$args[] = $array;
				continue;
			}
			if (!$value instanceof ConstantScalarType) {
				throw new DynamicQueryBuilderArgumentException();
			}

			$args[] = $value->getValue();
		}

		return $args;
	}

}
