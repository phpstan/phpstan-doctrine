<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PdoPgSQLDriver;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class FloatType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\FloatType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\FloatType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new \PHPStan\Type\FloatType(), new IntegerType());
	}

	public function getDatabaseInternalType(Driver $driver): Type
	{
		if ($driver instanceof PdoPgSQLDriver) {
			return new IntersectionType([
				new StringType(),
				new AccessoryNumericStringType(),
			]);
		}
		return new \PHPStan\Type\FloatType();
	}

}
