<?php declare(strict_types = 1);

use Cache\Adapter\PHPArray\ArrayCachePool;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\StaticPHPDriver;
use Doctrine\ORM\ORMSetup;

$config = ORMSetup::createConfiguration(
	true,
	__DIR__,
	new ArrayCachePool()
);

$config->setMetadataDriverImpl(new StaticPHPDriver([__DIR__ . '/Entities']));

$config->setProxyNamespace('PHPStan\\Doctrine\\UnitOfWorkChangeSetProxies');
$config->setAutoGenerateProxyClasses(true);

return new EntityManager(
	DriverManager::getConnection([
		'driver' => 'pdo_sqlite',
		'memory' => true,
	]),
	$config
);
