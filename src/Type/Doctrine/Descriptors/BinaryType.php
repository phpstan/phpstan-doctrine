<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\MixedType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\Type;

class BinaryType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'binary';
	}

	public function getWritableToPropertyType(): Type
	{
		return new ResourceType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new MixedType();
	}

}
