<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use PHPStan\Doctrine\Driver\DriverType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class BooleanType implements DoctrineTypeDescriptor, DoctrineTypeDriverAwareDescriptor
{

	/** @var bool */
	private $bleedingEdge;

	public function __construct(bool $bleedingEdge)
	{
		$this->bleedingEdge = $bleedingEdge;
	}

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

	public function getDatabaseInternalType(): Type
	{
		return TypeCombinator::union(
			new ConstantIntegerType(0),
			new ConstantIntegerType(1),
			new \PHPStan\Type\BooleanType()
		);
	}

	public function getDatabaseInternalTypeForDriver(Connection $connection): Type
	{
		$driverType = DriverType::detect($connection, $this->bleedingEdge);

		if ($driverType === DriverType::PGSQL || $driverType === DriverType::PDO_PGSQL) {
			return new \PHPStan\Type\BooleanType();
		}

		if (
			$driverType === DriverType::SQLITE3
			|| $driverType === DriverType::PDO_SQLITE
			|| $driverType === DriverType::MYSQLI
			|| $driverType === DriverType::PDO_MYSQL
		) {
			return TypeCombinator::union(
				new ConstantIntegerType(0),
				new ConstantIntegerType(1)
			);
		}

		// not yet supported driver, return the old implementation guess
		return $this->getDatabaseInternalType();
	}

}
