<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Driver;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class AsciiStringType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\AsciiStringType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new StringType();
	}

	public function getDatabaseInternalType(Driver $driver): Type
	{
		return new StringType();
	}

}
