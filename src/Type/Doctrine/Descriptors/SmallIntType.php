<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;

class SmallIntType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\SmallIntType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\IntegerType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\IntegerType();
	}

}
