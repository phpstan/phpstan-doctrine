<?php declare(strict_types = 1);

namespace PHPStan;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventManager;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\Proxy\ProxyFactory;

class DoctrineClassMetadataProvider
{

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string
     */
    private $repositoryClass;

    public function __construct(string $repositoryClass, array $mapping)
    {
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
        $this->repositoryClass = $repositoryClass;
    }

    private function setupMappingDriver(array $mapping): MappingDriver
    {
        $driver = new MappingDriverChain();
        foreach ($mapping as $namespace => $config) {
            switch ($config['type']) {
                case 'annotation':
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

    /**
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     * @throws \ReflectionException
     */
    public function getMetadataFor(string $className): Mapping\ClassMetadataInfo
    {
        return $this->em->getClassMetadata($className);
    }
    
}
