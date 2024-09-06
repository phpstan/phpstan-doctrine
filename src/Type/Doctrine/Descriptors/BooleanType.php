<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function in_array;

class BooleanType implements DoctrineTypeDescriptor, DoctrineTypeDriverAwareDescriptor
{

	private DriverDetector $driverDetector;

	public function __construct(DriverDetector $driverDetector)
	{
		$this->driverDetector = $driverDetector;
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
			new \PHPStan\Type\BooleanType(),
		);
	}

	public function getDatabaseInternalTypeForDriver(Connection $connection): Type
	{
		$driverType = $this->driverDetector->detect($connection);

		if ($driverType === DriverDetector::PGSQL || $driverType === DriverDetector::PDO_PGSQL) {
			return new \PHPStan\Type\BooleanType();
		}

		if (in_array($driverType, [
			DriverDetector::SQLITE3,
			DriverDetector::PDO_SQLITE,
			DriverDetector::MYSQLI,
			DriverDetector::PDO_MYSQL,
		], true)) {
			return TypeCombinator::union(
				new ConstantIntegerType(0),
				new ConstantIntegerType(1),
			);
		}

		// not yet supported driver, return the old implementation guess
		return $this->getDatabaseInternalType();
	}

}
