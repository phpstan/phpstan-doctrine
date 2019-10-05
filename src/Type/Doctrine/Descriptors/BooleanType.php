<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;

class BooleanType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'boolean';
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\BooleanType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\BooleanType();
	}

}
