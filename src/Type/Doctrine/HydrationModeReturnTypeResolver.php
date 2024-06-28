<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\AbstractQuery;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VoidType;

class HydrationModeReturnTypeResolver
{

	public function getMethodReturnTypeForHydrationMode(
		string $methodName,
		int $hydrationMode,
		Type $queryKeyType,
		Type $queryResultType
	): ?Type
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
			return null;
		}

		switch ($hydrationMode) {
			case AbstractQuery::HYDRATE_OBJECT:
				break;
			case AbstractQuery::HYDRATE_ARRAY:
				$queryResultType = $this->getArrayHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SIMPLEOBJECT:
				$queryResultType = $this->getSimpleObjectHydratedReturnType($queryResultType);
				break;
			default:
				return null;
		}

		if ($queryResultType === null) {
			return null;
		}

		switch ($methodName) {
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

				/** @var class-string $className */
				$className = $type->getClassName();
				if (!$objectManager->getMetadataFactory()->hasMetadataFor($className)) {
					return $traverse($type);
				}

				$mixedFound = true;

				return new MixedType();
			}
		);

		return $mixedFound ? null : $queryResultType;
	}

	private function getSimpleObjectHydratedReturnType(Type $queryResultType): ?Type
	{
		if ((new ObjectWithoutClassType())->isSuperTypeOf($queryResultType)->yes()) {
			return $queryResultType;
		}

		return null;
	}

}
