<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ObjectManager;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\VoidType;

class HydrationModeReturnTypeResolver
{

	public function getMethodReturnTypeForHydrationMode(
		string $methodName,
		Type $hydrationMode,
		Type $queryKeyType,
		Type $queryResultType,
		?ObjectManager $objectManager
	): ?Type
	{
		$isVoidType = (new VoidType())->isSuperTypeOf($queryResultType);

		if ($isVoidType->yes()) {
			// A void query result type indicates an UPDATE or DELETE query.
			// In this case all methods return the number of affected rows.
			return IntegerRangeType::fromInterval(0, null);
		}

		if ($isVoidType->maybe()) {
			// We can't be sure what the query type is, so we return the
			// declared return type of the method.
			return null;
		}

		if (!$hydrationMode instanceof ConstantIntegerType) {
			return null;
		}

		switch ($hydrationMode->getValue()) {
			case AbstractQuery::HYDRATE_OBJECT:
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
					$queryResultType,
				);
			default:
				if ($queryKeyType->isNull()->yes()) {
					return TypeCombinator::intersect(new ArrayType(
						new IntegerType(),
						$queryResultType,
					), new AccessoryArrayListType());
				}
				return new ArrayType(
					$queryKeyType,
					$queryResultType,
				);
		}
	}

	private function getSimpleObjectHydratedReturnType(Type $queryResultType): ?Type
	{
		if ((new ObjectWithoutClassType())->isSuperTypeOf($queryResultType)->yes()) {
			return $queryResultType;
		}

		return null;
	}

}
