<?php declare(strict_types = 1);

if (
	!is_file(__DIR__ . '/../vendor/doctrine/orm/src/Mapping/Driver/AnnotationDriver.php')
	&& !is_file(__DIR__ . '/../vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/AnnotationDriver.php')
) {
	require_once __DIR__ . '/../compatibility/AnnotationDriver.php';
}
