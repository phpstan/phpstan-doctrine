<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\Query\QueryType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;
use function in_array;
use function method_exists;
use function strtolower;

class QueryBuilderGetQueryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string|null */
	private $queryBuilderClass;

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		?string $queryBuilderClass
	)
	{
		$this->queryBuilderClass = $queryBuilderClass;
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getClass(): string
	{
		return $this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getQuery';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		$defaultReturnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->args,
			$methodReflection->getVariants()
		)->getReturnType();
		if (!$calledOnType instanceof QueryBuilderType) {
			return $defaultReturnType;
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return $defaultReturnType;
		}
		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!$objectManager instanceof $entityManagerInterface) {
			return $defaultReturnType;
		}

		/** @var \Doctrine\ORM\EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$queryBuilder = $objectManager->createQueryBuilder();

		foreach ($calledOnType->getMethodCalls() as $calledMethodCall) {
			if (!$calledMethodCall->name instanceof Identifier) {
				continue;
			}

			$methodName = $calledMethodCall->name->toString();
			$lowerMethodName = strtolower($methodName);
			if (in_array($lowerMethodName, [
				'setparameter',
				'setparameters',
			], true)) {
				continue;
			}

			if ($lowerMethodName === 'setfirstresult') {
				$queryBuilder->setFirstResult(0);
				continue;
			}

			if ($lowerMethodName === 'setmaxresults') {
				$queryBuilder->setMaxResults(10);
				continue;
			}

			if (!method_exists($queryBuilder, $methodName)) {
				continue;
			}

			try {
				$args = $this->processArgs($scope, $methodName, $calledMethodCall->args);
			} catch (DynamicQueryBuilderArgumentException $e) {
				// todo parameter "detectDynamicQueryBuilders" a hlasit jako error - pro oddebugovani
				return $defaultReturnType;
			}

			$queryBuilder->{$methodName}(...$args);
		}

		return new QueryType($queryBuilder->getDQL());
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
			if (
				$arg->value instanceof New_
				&& $value instanceof TypeWithClassName
				&& strpos($value->getClassName(), 'Doctrine\ORM\Query\Expr') === 0
			) {
				$className = $value->getClassName();
				$args[] = new $className(...$this->processArgs($scope, '__construct', $arg->value->args));
				continue;
			}
			// todo $qb->expr() support
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
