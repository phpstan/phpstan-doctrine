<?php declare(strict_types = 1);

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OrmProxies');
$config->setMetadataCacheImpl(new DoctrineProvider(new ArrayAdapter()));

$metadataDriver = new MappingDriverChain();
$metadataDriver->addDriver(new AnnotationDriver(
	new AnnotationReader(),
	[__DIR__ . '/data']
), 'PHPStan\\Rules\\Doctrine\\ORM\\');

if (PHP_VERSION_ID >= 80100) {
	$metadataDriver->addDriver(
		new AttributeDriver([__DIR__ . '/data-attributes']),
		'PHPStan\\Rules\\Doctrine\\ORMAttributes\\'
	);
}

$config->setMetadataDriverImpl($metadataDriver);

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
