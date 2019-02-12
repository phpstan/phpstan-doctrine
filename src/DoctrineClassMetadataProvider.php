<?php declare(strict_types = 1);

namespace PHPStan;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\Proxy\ProxyFactory;

class DoctrineClassMetadataProvider
{

	/** @var ?EntityManager */
	private $em;

	/** @var string */
	private $repositoryClass;

	/**
	 * @param string $repositoryClass
	 * @param mixed[] $mapping
	 */
	public function __construct(string $repositoryClass, array $mapping)
	{
		if (count($mapping) > 0) {
			$configuration = new Configuration();
			$configuration->setDefaultRepositoryClassName($repositoryClass);
			$configuration->setMetadataDriverImpl($this->setupMappingDriver($mapping));
			$configuration->setProxyDir('/dev/null');
			$configuration->setProxyNamespace('__DP__');
			$configuration->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
			$evm = new EventManager();
			$this->em = EntityManager::create(
				\Doctrine\DBAL\DriverManager::getConnection(['host' => '/:memory:', 'driver' => 'pdo_sqlite'], $configuration, $evm),
				$configuration,
				$evm
			);
		}
		$this->repositoryClass = $repositoryClass;
	}

	/**
	 * @param mixed[] $mapping
	 */
	private function setupMappingDriver(array $mapping): MappingDriver
	{
		$driver = new MappingDriverChain();
		foreach ($mapping as $namespace => $config) {
			switch ($config['type']) {
				case 'annotation':
					AnnotationRegistry::registerUniqueLoader('class_exists');
					$nested = new Mapping\Driver\AnnotationDriver(new AnnotationReader(), $config['paths']);
					break;
				case 'yml':
				case 'yaml':
					$nested = new Mapping\Driver\YamlDriver($config['paths']);
					break;
				case 'xml':
					$nested = new Mapping\Driver\XmlDriver($config['paths']);
					break;
				default:
					throw new \InvalidArgumentException('Unknown mapping type: ' . $config['type']);
			}
			$driver->addDriver($nested, $namespace);
		}
		return $driver;
	}

	public function getBaseRepositoryClass(): string
	{
		return $this->repositoryClass;
	}

	public function getRepositoryClass(string $className): string
	{
		if ($this->em === null) {
			return $this->getBaseRepositoryClass();
		}

		try {
			return $this->em->getClassMetadata($className)->customRepositoryClassName ?: $this->getBaseRepositoryClass();
		} catch (MappingException $e) {
			return $this->getBaseRepositoryClass();
		}
	}

}
