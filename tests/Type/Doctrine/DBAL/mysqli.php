<?php declare(strict_types = 1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('App\GeneratedProxy');
$config->setMetadataCache(new ArrayCachePool());
$config->setMetadataDriverImpl(new AnnotationDriver(
	new AnnotationReader(),
	[__DIR__ . '/data']
));

return new EntityManager(
	DriverManager::getConnection([
		'driver' => 'mysqli',
		'memory' => true,
	]),
	$config
);
