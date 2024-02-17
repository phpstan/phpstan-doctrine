<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

/**
 * @group platform
 * @group mysql
 */
class MysqlQueryResultTypeWalkerTest extends PDOQueryResultTypeWalkerTest
{

	protected static function getEntityManagerPath(): string
	{
		return __DIR__ . '/../data/QueryResult/entity-manager-mysql.php';
	}

}
