<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class SimpleArrayType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\SimpleArrayType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return TypeCombinator::intersect(new ArrayType(new IntegerType(), new StringType()), new AccessoryArrayListType());
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ArrayType(new MixedType(), new StringType());
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
