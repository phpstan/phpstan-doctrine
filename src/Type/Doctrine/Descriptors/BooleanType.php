<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class BooleanType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\BooleanType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\BooleanType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\BooleanType();
	}

	public function getDatabaseInternalType(): Type
	{
		return TypeCombinator::union(
			new \PHPStan\Type\Constant\ConstantIntegerType(0),
			new \PHPStan\Type\Constant\ConstantIntegerType(1)
		);
	}

}
