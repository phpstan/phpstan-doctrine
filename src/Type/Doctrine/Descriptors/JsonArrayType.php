<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

class JsonArrayType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return 'json_array';
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\ArrayType(new MixedType(), new MixedType());
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\ArrayType(new MixedType(), new MixedType());
	}

}
