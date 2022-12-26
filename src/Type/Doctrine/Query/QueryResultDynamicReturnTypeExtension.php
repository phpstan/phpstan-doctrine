<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\ORM\AbstractQuery;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\GenericTypeVariableResolver;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VoidType;
use function count;

final class QueryResultDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private const METHOD_HYDRATION_MODE_ARG = [
		'getResult' => 0,
		'toIterable' => 1,
		'execute' => 1,
		'executeIgnoreQueryCache' => 1,
		'executeUsingQueryCache' => 1,
		'getOneOrNullResult' => 0,
		'getSingleResult' => 0,
	];

	private const METHOD_HYDRATION_MODE = [
		'getArrayResult' => AbstractQuery::HYDRATE_ARRAY,
		'getScalarResult' => AbstractQuery::HYDRATE_SCALAR,
		'getSingleColumnResult' => AbstractQuery::HYDRATE_SCALAR_COLUMN,
		'getSingleScalarResult' => AbstractQuery::HYDRATE_SINGLE_SCALAR,
	];

	public function getClass(): string
	{
		return AbstractQuery::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return isset(self::METHOD_HYDRATION_MODE_ARG[$methodReflection->getName()])
			|| isset(self::METHOD_HYDRATION_MODE[$methodReflection->getName()]);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$methodName = $methodReflection->getName();

		if (isset(self::METHOD_HYDRATION_MODE[$methodName])) {
			$hydrationMode = new ConstantIntegerType(self::METHOD_HYDRATION_MODE[$methodName]);
		} elseif (isset(self::METHOD_HYDRATION_MODE_ARG[$methodName])) {
			$argIndex = self::METHOD_HYDRATION_MODE_ARG[$methodName];
			$args = $methodCall->getArgs();

			if (isset($args[$argIndex])) {
				$hydrationMode = $scope->getType($args[$argIndex]->value);
			} else {
				$parametersAcceptor = ParametersAcceptorSelector::selectSingle(
					$methodReflection->getVariants()
				);
				$parameter = $parametersAcceptor->getParameters()[$argIndex];
				$hydrationMode = $parameter->getDefaultValue() ?? new NullType();
			}
		} else {
			throw new ShouldNotHappenException();
		}

		$queryType = $scope->getType($methodCall->var);
		$queryResultType = $this->getQueryResultType($queryType);
		$queryKeyType = $this->getQueryKeyType($queryType);

		return $this->getMethodReturnTypeForHydrationMode(
			$methodReflection,
			$hydrationMode,
			$queryKeyType,
			$queryResultType
		);
	}

	private function getQueryResultType(Type $queryType): Type
	{
		if (!$queryType instanceof TypeWithClassName) {
			return new MixedType();
		}

		$resultType = GenericTypeVariableResolver::getType($queryType, AbstractQuery::class, 'TResult');
		if ($resultType === null) {
			return new MixedType();
		}

		return $resultType;
	}

	private function getQueryKeyType(Type $queryType): Type
	{
		if (!$queryType instanceof TypeWithClassName) {
			return new MixedType();
		}

		$resultType = GenericTypeVariableResolver::getType($queryType, AbstractQuery::class, 'TKey');
		if ($resultType === null) {
			return new MixedType();
		}

		return $resultType;
	}

	private function getMethodReturnTypeForHydrationMode(
		MethodReflection $methodReflection,
		Type $hydrationMode,
		Type $queryKeyType,
		Type $queryResultType
	): Type
	{
		$isVoidType = (new VoidType())->isSuperTypeOf($queryResultType);

		if ($isVoidType->yes()) {
			// A void query result type indicates an UPDATE or DELETE query.
			// In this case all methods return the number of affected rows.
			return new IntegerType();
		}

		if ($isVoidType->maybe()) {
			// We can't be sure what the query type is, so we return the
			// declared return type of the method.
			return $this->originalReturnType($methodReflection);
		}

		if (!$hydrationMode instanceof ConstantIntegerType) {
			return $this->originalReturnType($methodReflection);
		}

		switch ($hydrationMode->getValue()) {
			case AbstractQuery::HYDRATE_OBJECT:
				break;
			case AbstractQuery::HYDRATE_ARRAY:
				$queryResultType = $this->getArrayHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SCALAR:
				$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SINGLE_SCALAR:
				$queryResultType = $this->getSingleScalarHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SIMPLEOBJECT:
				$queryResultType = $this->getSimpleObjectHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SCALAR_COLUMN:
				$queryResultType = $this->getScalarColumnHydratedReturnType($queryResultType);
				break;
			default:
				return $this->originalReturnType($methodReflection);
		}

		switch ($methodReflection->getName()) {
			case 'getSingleResult':
				return $queryResultType;
			case 'getOneOrNullResult':
				return TypeCombinator::addNull($queryResultType);
			case 'toIterable':
				return new IterableType(
					$queryKeyType instanceof NullType ? new IntegerType() : $queryKeyType,
					$queryResultType
				);
			default:
				if ($queryKeyType instanceof NullType) {
					return AccessoryArrayListType::intersectWith(new ArrayType(
						new IntegerType(),
						$queryResultType
					));
				}
				return new ArrayType(
					$queryKeyType,
					$queryResultType
				);
		}
	}

	private function getArrayHydratedReturnType(Type $queryResultType): Type
	{
		return TypeTraverser::map(
			$queryResultType,
			static function (Type $type, callable $traverse): Type {
				$isObject = (new ObjectWithoutClassType())->isSuperTypeOf($type);
				if ($isObject->yes()) {
					return new ArrayType(new MixedType(), new MixedType());
				}
				if ($isObject->maybe()) {
					return new MixedType();
				}

				return $traverse($type);
			}
		);
	}

	private function getScalarHydratedReturnType(Type $queryResultType): Type
	{
		if (!$queryResultType instanceof ArrayType) {
			return new ArrayType(new MixedType(), new MixedType());
		}

		$itemType = $queryResultType->getItemType();
		$hasNoObject = (new ObjectWithoutClassType())->isSuperTypeOf($itemType)->no();
		$hasNoArray = $itemType->isArray()->no();

		if ($hasNoArray && $hasNoObject) {
			return $queryResultType;
		}

		return new ArrayType(new MixedType(), new MixedType());
	}

	private function getSimpleObjectHydratedReturnType(Type $queryResultType): Type
	{
		if ((new ObjectWithoutClassType())->isSuperTypeOf($queryResultType)->yes()) {
			return $queryResultType;
		}

		return new MixedType();
	}

	private function getSingleScalarHydratedReturnType(Type $queryResultType): Type
	{
		$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
		if (!$queryResultType instanceof ConstantArrayType) {
			return new ArrayType(new MixedType(), new MixedType());
		}

		$values = $queryResultType->getValueTypes();
		if (count($values) !== 1) {
			return new ArrayType(new MixedType(), new MixedType());
		}

		return $queryResultType;
	}

	private function getScalarColumnHydratedReturnType(Type $queryResultType): Type
	{
		$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
		if (!$queryResultType instanceof ConstantArrayType) {
			return new MixedType();
		}

		$values = $queryResultType->getValueTypes();
		if (count($values) !== 1) {
			return new MixedType();
		}

		return $queryResultType->getFirstIterableValueType();
	}

	private function originalReturnType(MethodReflection $methodReflection): Type
	{
		$parametersAcceptor = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		);

		return $parametersAcceptor->getReturnType();
	}

}
