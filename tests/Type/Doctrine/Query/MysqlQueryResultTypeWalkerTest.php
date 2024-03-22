<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

/**
 * @group platform
 * @group mysql
 */
class MysqlQueryResultTypeWalkerTest extends PDOQueryResultTypeWalkerTest
{
	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../data/QueryResult/config-mysql.neon',
		];
	}

	protected static function getEntityManagerPath(): string
	{
		return __DIR__ . '/../data/QueryResult/entity-manager-mysql.php';
	}

}
