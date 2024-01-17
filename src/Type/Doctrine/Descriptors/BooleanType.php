<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PdoPgSQLDriver;
use Doctrine\DBAL\Driver\PgSQL\Driver as PgSQLDriver;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class BooleanType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\BooleanType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\BooleanType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\BooleanType();
	}

	public function getDatabaseInternalType(Driver $driver): Type
	{
		if ($driver instanceof PgSQLDriver || $driver instanceof PdoPgSQLDriver) {
			return new \PHPStan\Type\BooleanType();
		}

		return TypeCombinator::union(
			new ConstantIntegerType(0),
			new ConstantIntegerType(1)
		);
	}

}
