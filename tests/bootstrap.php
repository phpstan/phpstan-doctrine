<?php declare(strict_types = 1);

use PHPStan\Testing\PHPStanTestCase;

require_once __DIR__ . '/../vendor/autoload.php';

PHPStanTestCase::getContainer();

require_once __DIR__ . '/orm-3-bootstrap.php';
require_once __DIR__ . '/dbal-4-bootstrap.php';
