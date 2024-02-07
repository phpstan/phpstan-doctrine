<?php declare(strict_types = 1);

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\DoctrineProvider;

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OrmProxies');

if (class_exists(DoctrineProvider::class)) {
	$config->setMetadataCacheImpl(new DoctrineProvider(new ArrayAdapter()));
}

$metadataDriver = new MappingDriverChain();

if (class_exists(AnnotationDriver::class) && class_exists(AnnotationReader::class)) {
	$metadataDriver->addDriver(
		new AnnotationDriver(
			new AnnotationReader(),
			[__DIR__ . '/Entities']
		),
		'QueryResult\Entities\\'
	);
	if (property_exists(Column::class, 'enumType') && PHP_VERSION_ID >= 80100) {
		$metadataDriver->addDriver(new AnnotationDriver(
			new AnnotationReader(),
			[__DIR__ . '/EntitiesEnum']
		), 'QueryResult\EntitiesEnum\\');
	}
} else {
	$metadataDriver->addDriver(
		new AttributeDriver([__DIR__ . '/Entities']),
		'QueryResult\Entities\\'
	);
	$metadataDriver->addDriver(
		new AttributeDriver([__DIR__ . '/EntitiesEnum']),
		'QueryResult\EntitiesEnum\\'
	);
}

$config->setMetadataDriverImpl($metadataDriver);

$connection = DriverManager::getConnection(
	[
		'driver' => 'pdo_sqlite',
		'memory' => true,
	],
	$config
);

return new EntityManager(
	$connection,
	$config,
	new EventManager()
);
