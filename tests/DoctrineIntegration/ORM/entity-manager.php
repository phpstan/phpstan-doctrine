<?php declare(strict_types = 1);

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;

AnnotationRegistry::registerUniqueLoader('class_exists');

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OrmProxies');
$config->setMetadataCacheImpl(new ArrayCache());

$config->setMetadataDriverImpl(
	new AnnotationDriver(
		new AnnotationReader(),
		[__DIR__ . '/data']
	)
);

return EntityManager::create(
	[
		'driver' => 'pdo_sqlite',
		'memory' => true,
	],
	$config
);
