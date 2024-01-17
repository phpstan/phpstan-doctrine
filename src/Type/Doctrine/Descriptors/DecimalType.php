<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as PdoSqliteDriver;
use Doctrine\DBAL\Driver\SQLite3\Driver as Sqlite3Driver;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class DecimalType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\DecimalType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType());
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new StringType(), new FloatType(), new IntegerType());
	}

	public function getDatabaseInternalType(Driver $driver): Type
	{
		if ($driver instanceof Sqlite3Driver || $driver instanceof PdoSqliteDriver) {
			return new FloatType();
		}

		// TODO use mixed as fallback for any untested driver or some guess?
		return new IntersectionType([
			new StringType(),
			new AccessoryNumericStringType(),
		]);
	}

}
