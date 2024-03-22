<?php

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;

class TestExecuteQuery
{

	public function test(Connection $connection, QueryCacheProfile $cacheProfile): void
	{
		$connection->executeQuery('SELECT foo from bar WHERE id = 1;');
		$connection->executeQuery(' SELECT foo from bar WHERE id = 1; ');
		$connection->executeQuery('UPDATE bar SET foo = NULL WHERE id = 1;');
		$connection->executeQuery(' UPDATE bar SET foo = NULL WHERE id = 1; ');

		$connection->executeCacheQuery('SELECT foo from bar WHERE id = 1;', [], [], $cacheProfile);
		$connection->executeCacheQuery(' SELECT foo from bar WHERE id = 1; ', [], [], $cacheProfile);
		$connection->executeCacheQuery('UPDATE bar SET foo = NULL WHERE id = 1;', [], [], $cacheProfile);
		$connection->executeCacheQuery(' UPDATE bar SET foo = NULL WHERE id = 1; ', [], [], $cacheProfile);

		$connection->executeQuery('UPDATE bar SET foo = NULL WHERE id = 1 RETURNING *;');
		$connection->executeCacheQuery('UPDATE bar SET foo = NULL WHERE id = 1 RETURNING *;', [], [], $cacheProfile);
	}

}
