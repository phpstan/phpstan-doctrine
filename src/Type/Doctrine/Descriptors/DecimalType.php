<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class DecimalType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'decimal';
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new \PHPStan\Type\StringType(), new \PHPStan\Type\FloatType(), new \PHPStan\Type\IntegerType());
	}

}
