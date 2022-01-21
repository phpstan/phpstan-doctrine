<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class BigIntType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\BigIntType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new StringType(), new IntegerType());
	}

	public function getDatabaseInternalType(): Type
	{
		return new IntegerType();
	}

}
