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
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;
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

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

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

		return $this->getMethodReturnTypeForHydrationMode(
			$methodReflection,
			$hydrationMode,
			$queryType->getTemplateType(AbstractQuery::class, 'TKey'),
			$queryType->getTemplateType(AbstractQuery::class, 'TResult')
		);
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

		$singleResult = false;
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
				$singleResult = true;
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

		if (null === $queryResultType) {
			return $this->originalReturnType($methodReflection);
		}

		switch ($methodReflection->getName()) {
			case 'getSingleResult':
				return $queryResultType;
			case 'getOneOrNullResult':
				$nullableQueryResultType = TypeCombinator::addNull($queryResultType);
				if ($queryResultType instanceof BenevolentUnionType) {
					$nullableQueryResultType = TypeUtils::toBenevolentUnion($nullableQueryResultType);
				}

				return $nullableQueryResultType;
			case 'toIterable':
				return new IterableType(
					$queryKeyType->isNull()->yes() ? new IntegerType() : $queryKeyType,
					$queryResultType
				);
			default:
				if ($singleResult) {
					return $queryResultType;
				}

				if ($queryKeyType->isNull()->yes()) {
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

	/**
	 * When we're array-hydrating object, we're not sure of the shape of the array.
	 * We could return `new ArrayTyp(new MixedType(), new MixedType())`
	 * but the lack of precision in the array keys/values would give false positive.
	 *
	 * @see https://github.com/phpstan/phpstan-doctrine/pull/412#issuecomment-1497092934
	 */
	private function getArrayHydratedReturnType(Type $queryResultType): ?Type
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();

		$mixedFound = false;
		$queryResultType = TypeTraverser::map(
			$queryResultType,
			static function (Type $type, callable $traverse) use ($objectManager, &$mixedFound): Type {
				$isObject = (new ObjectWithoutClassType())->isSuperTypeOf($type);
				if ($isObject->no()) {
					return $traverse($type);
				}
				if (
					$isObject->maybe()
					|| !$type instanceof TypeWithClassName
					|| $objectManager === null
				) {
					$mixedFound = true;

					return new MixedType();
				}

				if (!$objectManager->getMetadataFactory()->hasMetadataFor($type->getClassName())) {
					return $traverse($type);
				}

				$mixedFound = true;

				return new MixedType();
			}
		);

		return $mixedFound ? null : $queryResultType;
	}

	/**
	 * When we're scalar-hydrating object, we're not sure of the shape of the array.
	 * We could return `new ArrayTyp(new MixedType(), new MixedType())`
	 * but the lack of precision in the array keys/values would give false positive.
	 *
	 * @see https://github.com/phpstan/phpstan-doctrine/pull/453#issuecomment-1895415544
	 */
	private function getScalarHydratedReturnType(Type $queryResultType): ?Type
	{
		if (!$queryResultType->isArray()->yes()) {
			return null;
		}

		foreach ($queryResultType->getArrays() as $arrayType) {
			$itemType = $arrayType->getItemType();

			if (
				!(new ObjectWithoutClassType())->isSuperTypeOf($itemType)->no()
				|| !$itemType->isArray()->no()
			) {
				// We could return `new ArrayTyp(new MixedType(), new MixedType())`
				// but the lack of precision in the array keys/values would give false positive
				// @see https://github.com/phpstan/phpstan-doctrine/pull/453#issuecomment-1895415544
				return null;
			}
		}

		return $queryResultType;
	}

	private function getSimpleObjectHydratedReturnType(Type $queryResultType): ?Type
	{
		if ((new ObjectWithoutClassType())->isSuperTypeOf($queryResultType)->yes()) {
			return $queryResultType;
		}

		return null;
	}

	private function getSingleScalarHydratedReturnType(Type $queryResultType): ?Type
	{
		$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
		if (null === $queryResultType || !$queryResultType->isConstantArray()->yes()) {
			return null;
		}

		$types = [];
		foreach ($queryResultType->getConstantArrays() as $constantArrayType) {
			$values = $constantArrayType->getValueTypes();
			if (count($values) !== 1) {
				return null;
			}

			$types[] = $constantArrayType->getFirstIterableValueType();
		}

		return TypeCombinator::union(...$types);
	}

	private function getScalarColumnHydratedReturnType(Type $queryResultType): ?Type
	{
		$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
		if (null === $queryResultType || !$queryResultType->isConstantArray()->yes()) {
			return null;
		}

		$types = [];
		foreach ($queryResultType->getConstantArrays() as $constantArrayType) {
			$values = $constantArrayType->getValueTypes();
			if (count($values) !== 1) {
				return null;
			}

			$types[] = $constantArrayType->getFirstIterableValueType();
		}

		return TypeCombinator::union(...$types);
	}

	private function originalReturnType(MethodReflection $methodReflection): Type
	{
		$parametersAcceptor = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		);

		return $parametersAcceptor->getReturnType();
	}

}
