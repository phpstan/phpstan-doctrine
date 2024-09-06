<?php declare(strict_types = 1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OdmProxies');
$config->setMetadataCache(new ArrayCachePool());
$config->setHydratorDir(__DIR__);
$config->setHydratorNamespace('PHPstan\Doctrine\OdmHydrators');

$config->setMetadataDriverImpl(
	new AnnotationDriver(
		new AnnotationReader(),
		[__DIR__ . '/data'],
	),
);

return DocumentManager::create(
	null,
	$config,
);
