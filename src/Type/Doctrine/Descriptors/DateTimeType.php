<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use DateTime;
use DateTimeInterface;
use PHPStan\Type\Type;

class DateTimeType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\DateTimeType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\ObjectType(DateTime::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\ObjectType(DateTimeInterface::class);
	}

	public function getDatabaseInternalType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

}
