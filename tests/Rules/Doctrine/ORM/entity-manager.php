<?php declare(strict_types = 1);

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;

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

\Doctrine\DBAL\Types\Type::overrideType(
	'date',
	\Doctrine\DBAL\Types\DateTimeImmutableType::class
);

return EntityManager::create(
	[
		'driver' => 'pdo_sqlite',
		'memory' => true,
	],
	$config
);
