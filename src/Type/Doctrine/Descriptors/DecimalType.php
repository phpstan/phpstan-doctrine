<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class DecimalType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\DecimalType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return TypeCombinator::intersect(new \PHPStan\Type\StringType(), new AccessoryNumericStringType());
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new \PHPStan\Type\StringType(), new \PHPStan\Type\FloatType(), new \PHPStan\Type\IntegerType());
	}

}
