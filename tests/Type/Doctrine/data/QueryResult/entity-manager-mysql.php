<?php declare(strict_types = 1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Column;
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
	[__DIR__ . '/Entities']
), 'QueryResult\Entities\\');

if (property_exists(Column::class, 'enumType') && PHP_VERSION_ID >= 80100) {
	$metadataDriver->addDriver(new AnnotationDriver(
		new AnnotationReader(),
		[__DIR__ . '/EntitiesEnum']
	), 'QueryResult\EntitiesEnum\\');
}

$config->setMetadataDriverImpl($metadataDriver);

return new EntityManager(
	DriverManager::getConnection([
		'driver' => 'mysql',
		'url' => 'mysql://root@127.0.0.1:3306/phpstan_doctrine',
	]),
	$config
);
