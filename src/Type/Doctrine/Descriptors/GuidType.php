<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class GuidType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'guid';
	}

	public function getWritableToPropertyType(): Type
	{
		return new StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new StringType();
	}

}
