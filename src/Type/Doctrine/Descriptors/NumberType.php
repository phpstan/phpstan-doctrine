<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class NumberType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\NumberType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectType('BcMath\Number');
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ObjectType('BcMath\Number');
	}

	public function getDatabaseInternalType(): Type
	{
		return (new ObjectType('BcMath\Number'))->toString();
	}

}
