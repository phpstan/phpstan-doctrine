<?php

namespace ConnectionTransactional;

use Doctrine\DBAL\Connection;
use function PHPStan\Testing\assertType;

class MyConnection extends Connection
{

}

function (Connection $connection): void {
	$result = $connection->transactional(function ($db) {
		assertType('Doctrine\DBAL\Connection', $db);

		return 1;
	});
	assertType('1', $result);
};

function (MyConnection $connection): void {
	$result = $connection->transactional(function ($db) {
		assertType('ConnectionTransactional\MyConnection', $db);

		return 'foo';
	});
	assertType("'foo'", $result);

	$connection->transactional(static function (MyConnection $db): void {
	});
};
