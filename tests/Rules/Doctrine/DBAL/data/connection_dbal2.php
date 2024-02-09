<?php

namespace PHPStan\Rules\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

function check(Connection  $connection, array $data) {

	$connection->executeQuery(
		'SELECT 1 FROM table WHERE a IN (?) AND b = ?',
		[

			$data,
			3
		]
	);

	$connection->fetchOne(
		'SELECT 1 FROM table WHERE a IN (:a) AND b = :b',
		[

			'a' => $data,
			'b' => 3
		]
	);

	$connection->fetchOne(
		'SELECT 1 FROM table WHERE a IN (:a) AND b = :b',
		[
			'a' => $data,
			'b' => 3
		],
		[
			'b' => ParameterType::INTEGER,
		]
	);

	$connection->fetchOne(
		'SELECT 1 FROM table WHERE a IN (:a) AND b = :b',
		[
			'a' => $data,
			'b' => 3
		],
		[
			'a' => ParameterType::INTEGER,
			'b' => ParameterType::INTEGER,
		]
	);


	$connection->fetchOne(
		'SELECT 1 FROM table WHERE a IN (:a) AND b = :b',
		[
			'a' => $data,
			'b' => 3
		],
		[
			'a' => Connection::PARAM_INT_ARRAY,
			'b' => ParameterType::INTEGER,
		]
	);

}
