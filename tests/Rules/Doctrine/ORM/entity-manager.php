<?php declare(strict_types = 1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;

$config = new Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OrmProxies');
$config->setMetadataCache(new ArrayCachePool());

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

Type::overrideType(
	'date',
	DateTimeImmutableType::class
);

return new EntityManager(
	DriverManager::getConnection([
		'driver' => 'pdo_sqlite',
		'memory' => true,
	]),
	$config
);
