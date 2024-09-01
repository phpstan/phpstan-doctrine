<?php

namespace PDOResultRowCount;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Driver\Result as DriverResult;
use function PHPStan\Testing\assertType;

function (Result $r): void {
	assertType('int', $r->rowCount());
};

function (DriverResult $r): void {
	assertType('int', $r->rowCount());
};
