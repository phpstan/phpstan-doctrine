<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Ramsey;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

class UuidBinaryOrderedTimeTypeDescriptor implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return UuidBinaryOrderedTimeType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\ObjectType(UuidInterface::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(
			new \PHPStan\Type\StringType(),
			new \PHPStan\Type\ObjectType(UuidInterface::class)
		);
	}

}
