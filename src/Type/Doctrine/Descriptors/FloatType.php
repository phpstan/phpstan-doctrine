<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function in_array;

class FloatType implements DoctrineTypeDescriptor, DoctrineTypeDriverAwareDescriptor
{

	/** @var DriverDetector */
	private $driverDetector;

	public function __construct(DriverDetector $driverDetector)
	{
		$this->driverDetector = $driverDetector;
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
		return TypeCombinator::union(
			new \PHPStan\Type\FloatType(),
			new IntersectionType([
				new StringType(),
				new AccessoryNumericStringType(),
			])
		);
	}

	public function getDatabaseInternalTypeForDriver(Connection $connection): Type
	{
		$driverType = $this->driverDetector->detect($connection);

		if ($driverType === DriverDetector::PDO_PGSQL) {
			return new IntersectionType([
				new StringType(),
				new AccessoryNumericStringType(),
			]);
		}

		if (in_array($driverType, [
			DriverDetector::SQLITE3,
			DriverDetector::PDO_SQLITE,
			DriverDetector::MYSQLI,
			DriverDetector::PDO_MYSQL,
			DriverDetector::PGSQL,
		], true)) {
			return new \PHPStan\Type\FloatType();
		}

		// not yet supported driver, return the old implementation guess
		return $this->getDatabaseInternalType();
	}

}
