<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class BigIntType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'bigint';
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new \PHPStan\Type\StringType(), new \PHPStan\Type\IntegerType());
	}

}
