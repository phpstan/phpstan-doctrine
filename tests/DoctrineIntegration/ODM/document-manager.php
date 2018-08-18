<?php declare(strict_types = 1);

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;

AnnotationRegistry::registerUniqueLoader('class_exists');

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OdmProxies');
$config->setMetadataCacheImpl(new ArrayCache());
$config->setHydratorDir(__DIR__);
$config->setHydratorNamespace('PHPstan\Doctrine\OdmHydrators');

$config->setMetadataDriverImpl(
	new AnnotationDriver(
		new AnnotationReader(),
		[__DIR__ . '/data']
	)
);

return DocumentManager::create(
	null,
	$config
);
