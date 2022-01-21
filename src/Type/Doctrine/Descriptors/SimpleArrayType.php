<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\ArrayType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class SimpleArrayType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\SimpleArrayType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ArrayType(new MixedType(), new MixedType());
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ArrayType(new MixedType(), new MixedType());
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
