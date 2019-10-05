<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;

class StringType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'string';
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

}
