<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use PHPStan\Doctrine\Driver\DriverType;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class FloatType implements DoctrineTypeDescriptor, DoctrineTypeDriverAwareDescriptor
{

	/** @var bool */
	private $bleedingEdge;

	public function __construct(bool $bleedingEdge)
	{
		$this->bleedingEdge = $bleedingEdge;
	}

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

	public function getDatabaseInternalType(): Type
	{
		return TypeCombinator::union(new \PHPStan\Type\FloatType(), new IntegerType());
	}

	public function getDatabaseInternalTypeForDriver(Connection $connection): Type
	{
		$driverType = DriverType::detect($connection, $this->bleedingEdge);

		if ($driverType === DriverType::PDO_PGSQL) {
			return new IntersectionType([
				new StringType(),
				new AccessoryNumericStringType(),
			]);
		}

		if (
			$driverType === DriverType::SQLITE3
			|| $driverType === DriverType::PDO_SQLITE
			|| $driverType === DriverType::MYSQLI
			|| $driverType === DriverType::PDO_MYSQL
			|| $driverType === DriverType::PGSQL
		) {
			return new \PHPStan\Type\FloatType();
		}

		// not yet supported driver, return the old implementation guess
		return $this->getDatabaseInternalType();
	}

}
