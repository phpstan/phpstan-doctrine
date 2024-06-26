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
use function get_class;
use function is_a;

class DriverDetector
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
	public function detect(Connection $connection): ?string
	{
		$driver = $connection->getDriver();

		return $this->deduceFromDriverClass(get_class($driver)) ?? $this->deduceFromParams($connection);
	}

	/**
	 * @return array<mixed>
	 */
	public function detectDriverOptions(Connection $connection): array
	{
		return $connection->getParams()['driverOptions'] ?? [];
	}

	/**
	 * @return self::*|null
	 */
	private function deduceFromDriverClass(string $driverClass): ?string
	{
		if (is_a($driverClass, MysqliDriver::class, true)) {
			return self::MYSQLI;
		}

		if (is_a($driverClass, PdoMysqlDriver::class, true)) {
			return self::PDO_MYSQL;
		}

		if (is_a($driverClass, PdoSQLiteDriver::class, true)) {
			return self::PDO_SQLITE;
		}

		if (is_a($driverClass, PdoSqlSrvDriver::class, true)) {
			return self::PDO_SQLSRV;
		}

		if (is_a($driverClass, PdoOciDriver::class, true)) {
			return self::PDO_OCI;
		}

		if (is_a($driverClass, PdoPgSQLDriver::class, true)) {
			return self::PDO_PGSQL;
		}

		if (is_a($driverClass, SQLite3Driver::class, true)) {
			return self::SQLITE3;
		}

		if (is_a($driverClass, PgSQLDriver::class, true)) {
			return self::PGSQL;
		}

		if (is_a($driverClass, SqlSrvDriver::class, true)) {
			return self::SQLSRV;
		}

		if (is_a($driverClass, Oci8Driver::class, true)) {
			return self::OCI8;
		}

		if (is_a($driverClass, IbmDb2Driver::class, true)) {
			return self::IBM_DB2;
		}

		return null;
	}

	/**
	 * @return self::*|null
	 */
	private function deduceFromParams(Connection $connection): ?string
	{
		$params = $connection->getParams();

		if (isset($params['driver'])) {
			switch ($params['driver']) {
				case 'pdo_mysql':
					return self::PDO_MYSQL;
				case 'pdo_sqlite':
					return self::PDO_SQLITE;
				case 'pdo_pgsql':
					return self::PDO_PGSQL;
				case 'pdo_oci':
					return self::PDO_OCI;
				case 'oci8':
					return self::OCI8;
				case 'ibm_db2':
					return self::IBM_DB2;
				case 'pdo_sqlsrv':
					return self::PDO_SQLSRV;
				case 'mysqli':
					return self::MYSQLI;
				case 'pgsql': // @phpstan-ignore-line never matches on PHP 7.3- with old dbal
					return self::PGSQL;
				case 'sqlsrv':
					return self::SQLSRV;
				case 'sqlite3': // @phpstan-ignore-line never matches on PHP 7.3- with old dbal
					return self::SQLITE3;
				default:
					return null;
			}
		}

		if (isset($params['driverClass'])) {
			return $this->deduceFromDriverClass($params['driverClass']);
		}

		return null;
	}

}
