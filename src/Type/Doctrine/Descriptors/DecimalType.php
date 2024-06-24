<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use PHPStan\Doctrine\Driver\DriverType;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class DecimalType implements DoctrineTypeDescriptor, DoctrineTypeDriverAwareDescriptor
{

	/** @var bool */
	private $bleedingEdge;

	public function __construct(bool $bleedingEdge)
	{
		$this->bleedingEdge = $bleedingEdge;
	}

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

	public function getDatabaseInternalType(): Type
	{
		return TypeCombinator::union(new FloatType(), new IntegerType());
	}

	public function getDatabaseInternalTypeForDriver(Connection $connection): Type
	{
		$driverType = DriverType::detect($connection, $this->bleedingEdge);

		if ($driverType === DriverType::SQLITE3 || $driverType === DriverType::PDO_SQLITE) {
			return TypeCombinator::union(new FloatType(), new IntegerType());
		}

		if (
			$driverType === DriverType::MYSQLI
			|| $driverType === DriverType::PDO_MYSQL
			|| $driverType === DriverType::PGSQL
			|| $driverType === DriverType::PDO_PGSQL
		) {
			return new IntersectionType([
				new StringType(),
				new AccessoryNumericStringType(),
			]);
		}

		// not yet supported driver, return the old implementation guess
		return $this->getDatabaseInternalType();
	}

}
