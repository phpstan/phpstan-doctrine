<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class FloatType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'float';
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\FloatType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new \PHPStan\Type\FloatType(), new \PHPStan\Type\IntegerType());
	}

}
