<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use DateTime;
use DateTimeInterface;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class DateType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		// @phpstan-ignore classConstant.deprecatedClass (To be removed when the type is removed)
		return \Doctrine\DBAL\Types\DateType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectType(DateTime::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ObjectType(DateTimeInterface::class);
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
