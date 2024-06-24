<?php declare(strict_types = 1);

namespace PHPStan\Doctrine\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\IBMDB2\Driver as IbmDb2Driver;
use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\OCI8\Driver as Oci8Driver;
use Doctrine\DBAL\Driver\PDO\MySQL\Driver as PdoMysqlDriver;
use Doctrine\DBAL\Driver\PDO\OCI\Driver as PdoOciDriver;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver as PdoPgSQLDriver;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver as PdoSQLiteDriver;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Driver as PdoSqlSrvDriver;
use Doctrine\DBAL\Driver\PgSQL\Driver as PgSQLDriver;
use Doctrine\DBAL\Driver\SQLite3\Driver as SQLite3Driver;
use Doctrine\DBAL\Driver\SQLSrv\Driver as SqlSrvDriver;
use mysqli;
use PDO;
use SQLite3;
use Throwable;
use function get_resource_type;
use function is_resource;
use function method_exists;
use function strpos;

class DriverType
{

	public const IBM_DB2 = 'ibm_db2';
	public const MYSQLI = 'mysqli';
	public const OCI8 = 'oci8';
	public const PDO_MYSQL = 'pdo_mysql';
	public const PDO_OCI = 'pdo_oci';
	public const PDO_PGSQL = 'pdo_pgsql';
	public const PDO_SQLITE = 'pdo_sqlite';
	public const PDO_SQLSRV = 'pdo_sqlsrv';
	public const PGSQL = 'pgsql';
	public const SQLITE3 = 'sqlite3';
	public const SQLSRV = 'sqlsrv';


	/**
	 * @return self::*|null
	 */
	public static function detect(Connection $connection, bool $failOnInvalidConnection): ?string
	{
		$driver = $connection->getDriver();

		if ($driver instanceof MysqliDriver) {
			return self::MYSQLI;
		}

		if ($driver instanceof PdoMysqlDriver) {
			return self::PDO_MYSQL;
		}

		if ($driver instanceof PdoSQLiteDriver) {
			return self::PDO_SQLITE;
		}

		if ($driver instanceof PdoSqlSrvDriver) {
			return self::PDO_SQLSRV;
		}

		if ($driver instanceof PdoOciDriver) {
			return self::PDO_OCI;
		}

		if ($driver instanceof PdoPgSQLDriver) {
			return self::PDO_PGSQL;
		}

		if ($driver instanceof SQLite3Driver) {
			return self::SQLITE3;
		}

		if ($driver instanceof PgSQLDriver) {
			return self::PGSQL;
		}

		if ($driver instanceof SqlSrvDriver) {
			return self::SQLSRV;
		}

		if ($driver instanceof Oci8Driver) {
			return self::OCI8;
		}

		if ($driver instanceof IbmDb2Driver) {
			return self::IBM_DB2;
		}

		// fallback to connection-based detection when driver is wrapped by middleware

		if (!method_exists($connection, 'getNativeConnection')) {
			return null; // dbal < 3.3 (released in 2022-01)
		}

		try {
			$nativeConnection = $connection->getNativeConnection();
		} catch (Throwable $e) {
			if ($failOnInvalidConnection) {
				throw $e;
			}
			return null; // connection cannot be established
		}

		if ($nativeConnection instanceof mysqli) {
			return self::MYSQLI;
		}

		if ($nativeConnection instanceof SQLite3) {
			return self::SQLITE3;
		}

		if ($nativeConnection instanceof \PgSql\Connection) {
			return self::PGSQL;
		}

		if ($nativeConnection instanceof PDO) {
			$driverName = $nativeConnection->getAttribute(PDO::ATTR_DRIVER_NAME);

			if ($driverName === 'mysql') {
				return self::PDO_MYSQL;
			}

			if ($driverName === 'sqlite') {
				return self::PDO_SQLITE;
			}

			if ($driverName === 'pgsql') {
				return self::PDO_PGSQL;
			}

			if ($driverName === 'oci') {  // semi-verified (https://stackoverflow.com/questions/10090709/get-current-pdo-driver-from-existing-connection/10090754#comment12923198_10090754)
				return self::PDO_OCI;
			}

			if ($driverName === 'sqlsrv') {
				return self::PDO_SQLSRV;
			}
		}

		if (is_resource($nativeConnection)) {
			$resourceType = get_resource_type($nativeConnection);

			if (strpos($resourceType, 'oci') !== false) { // not verified
				return self::OCI8;
			}

			if (strpos($resourceType, 'db2') !== false) { // not verified
				return self::IBM_DB2;
			}

			if (strpos($resourceType, 'SQL Server Connection') !== false) {
				return self::SQLSRV;
			}

			if (strpos($resourceType, 'pgsql link') !== false) {
				return self::PGSQL;
			}
		}

		return null;
	}

}
