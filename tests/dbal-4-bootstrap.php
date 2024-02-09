<?php declare(strict_types = 1);

if (
	!is_file(__DIR__ . '/../vendor/doctrine/dbal/src/Types/ArrayType.php')
	&& !is_file(__DIR__ . '/../vendor/doctrine/dbal/lib/Doctrine/DBAL/Types/ArrayType.php')
) {
	require_once __DIR__ . '/../compatibility/ArrayType.php';
}
