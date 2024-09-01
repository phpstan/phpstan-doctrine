<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

class RowCountMethodDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	/** @var string */
	private $class;

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var DriverDetector */
	private $driverDetector;

	/** @var ReflectionProvider */
	private $reflectionProvider;

	public function __construct(
		string $class,
		ObjectMetadataResolver $objectMetadataResolver,
		DriverDetector $driverDetector,
		ReflectionProvider $reflectionProvider
	)
	{
		$this->class = $class;
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->driverDetector = $driverDetector;
		$this->reflectionProvider = $reflectionProvider;
	}

	public function getClass(): string
	{
		return $this->class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'rowCount';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if (!$objectManager instanceof EntityManagerInterface) {
			return null;
		}

		$connection = $objectManager->getConnection();
		$driver = $this->driverDetector->detect($connection);
		if ($driver === null) {
			return null;
		}

		$resultClass = $this->getResultClass($driver);
		if ($resultClass === null) {
			return null;
		}

		if (!$this->reflectionProvider->hasClass($resultClass)) {
			return null;
		}

		$resultReflection = $this->reflectionProvider->getClass($resultClass);
		if (!$resultReflection->hasNativeMethod('rowCount')) {
			return null;
		}

		$rowCountMethod = $resultReflection->getNativeMethod('rowCount');
		$variant = ParametersAcceptorSelector::selectSingle($rowCountMethod->getVariants());

		return $variant->getReturnType();
	}

	/**
	 * @param DriverDetector::* $driver
	 * @return class-string<DriverResult>|null
	 */
	private function getResultClass(string $driver): ?string
	{
		switch ($driver) {
			case DriverDetector::IBM_DB2:
				return 'Doctrine\DBAL\Driver\IBMDB2\Result';
			case DriverDetector::MYSQLI:
				return 'Doctrine\DBAL\Driver\Mysqli\Result';
			case DriverDetector::OCI8:
				return 'Doctrine\DBAL\Driver\OCI8\Result';
			case DriverDetector::PDO_MYSQL:
			case DriverDetector::PDO_OCI:
			case DriverDetector::PDO_PGSQL:
			case DriverDetector::PDO_SQLITE:
			case DriverDetector::PDO_SQLSRV:
				return 'Doctrine\DBAL\Driver\PDO\Result';
			case DriverDetector::PGSQL:
				return 'Doctrine\DBAL\Driver\PgSQL\Result'; // @phpstan-ignore return.type
			case DriverDetector::SQLITE3:
				return 'Doctrine\DBAL\Driver\SQLite3\Result'; // @phpstan-ignore return.type
			case DriverDetector::SQLSRV:
				return 'Doctrine\DBAL\Driver\SQLSrv\Result';
		}

		return null;
	}

}
