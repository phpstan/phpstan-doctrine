<?php declare(strict_types = 1);

namespace PHPStan\Doctrine\Mapping;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use function class_exists;
use function count;
use function method_exists;
use const PHP_VERSION_ID;

class ClassMetadataFactory extends \Doctrine\ORM\Mapping\ClassMetadataFactory
{

	private string $tmpDir;

	public function __construct(string $tmpDir)
	{
		$this->tmpDir = $tmpDir;
	}

	protected function initialize(): void
	{
		$drivers = [];
		if (class_exists(AnnotationDriver::class) && class_exists(AnnotationReader::class)) {
			$docParser = new DocParser();
			$docParser->setIgnoreNotImportedAnnotations(true);
			$drivers[] = new AnnotationDriver(new AnnotationReader($docParser));
		}
		if (class_exists(AttributeDriver::class) && PHP_VERSION_ID >= 80000) {
			$drivers[] = new AttributeDriver([]);
		}

		$config = new Configuration();
		$config->setMetadataDriverImpl(count($drivers) === 1 ? $drivers[0] : new MappingDriverChain($drivers));

		// @phpstan-ignore function.impossibleType, function.alreadyNarrowedType (Available since Doctrine ORM 3.4)
		if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
			$config->enableNativeLazyObjects(true);
		} else {
			$config->setAutoGenerateProxyClasses(true);
			$config->setProxyDir($this->tmpDir);
			$config->setProxyNamespace('__PHPStanDoctrine__\\Proxy');
		}

		$connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'memory' => true,
		], $config);

		if (!method_exists(EntityManager::class, 'create')) {
			$em = new EntityManager($connection, $config);
		} else {
			$em = EntityManager::create($connection, $config);
		}

		$this->setEntityManager($em);
		parent::initialize();

		$this->initialized = true;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return ClassMetadata<T>
	 */
	protected function newClassMetadataInstance($className): ClassMetadata
	{
		return new ClassMetadata($className);
	}

}
