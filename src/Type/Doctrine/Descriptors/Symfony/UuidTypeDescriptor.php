<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Symfony;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

class UuidTypeDescriptor implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return UuidType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectType(Uuid::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ObjectType(Uuid::class);
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
