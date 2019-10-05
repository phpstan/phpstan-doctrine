<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;

class ObjectType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'object';
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectWithoutClassType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ObjectWithoutClassType();
	}

}
