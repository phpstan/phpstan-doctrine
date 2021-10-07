<?php declare(strict_types = 1);

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ODM\MongoDB;
use Doctrine\ORM;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

$config = new MongoDB\Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OdmProxies');
$config->setMetadataCacheImpl(new ArrayCache());
$config->setHydratorDir(__DIR__);
$config->setHydratorNamespace('PHPstan\Doctrine\OdmHydrators');

$config->setMetadataDriverImpl(
	new MongoDB\Mapping\Driver\AnnotationDriver(
		new AnnotationReader(),
		[__DIR__ . '/ObjectMetadataResolverTest.php']
	)
);

$documentManager = MongoDB\DocumentManager::create(
	null,
	$config
);

$config = new ORM\Configuration();
$config->setProxyDir(__DIR__);
$config->setProxyNamespace('PHPstan\Doctrine\OrmProxies');
$config->setMetadataCacheImpl(new ArrayCache());

$config->setMetadataDriverImpl(
	new ORM\Mapping\Driver\AnnotationDriver(
		new AnnotationReader(),
		[__DIR__ . '/ObjectMetadataResolverTest.php']
	)
);

$entityManager = ORM\EntityManager::create(
	[
		'driver' => 'pdo_sqlite',
		'memory' => true,
	],
	$config
);

$metadataFactory = new class($documentManager, $entityManager) implements ClassMetadataFactory
{

	/** @var MongoDB\DocumentManager */
	private $documentManager;

	/** @var ORM\EntityManager */
	private $entityManager;

	public function __construct(MongoDB\DocumentManager $documentManager, ORM\EntityManager $entityManager)
	{
		$this->documentManager = $documentManager;
		$this->entityManager = $entityManager;
	}

	public function getAllMetadata(): array
	{
		return array_merge(
			$this->documentManager->getMetadataFactory()->getAllMetadata(),
			$this->entityManager->getMetadataFactory()->getAllMetadata()
		);
	}

	/**
	 * @param string $className
	 */
	public function getMetadataFor($className): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param string $className
	 */
	public function isTransient($className): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param string $className
	 */
	public function hasMetadataFor($className): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param string        $className
	 * @param ClassMetadata $class
	 */
	public function setMetadataFor($className, $class): void
	{
		throw new \Exception(__FILE__);
	}

};

return new class($documentManager, $entityManager, $metadataFactory) implements ObjectManager
{

	/** @var MongoDB\DocumentManager */
	private $documentManager;

	/** @var ORM\EntityManager */
	private $entityManager;

	/** @var ClassMetadataFactory */
	private $classMetadataFactory;

	public function __construct(MongoDB\DocumentManager $documentManager, ORM\EntityManager $entityManager, ClassMetadataFactory $classMetadataFactory)
	{
		$this->documentManager = $documentManager;
		$this->entityManager = $entityManager;
		$this->classMetadataFactory = $classMetadataFactory;
	}

	/**
	 * @param string $className
	 */
	public function getRepository($className): ObjectRepository
	{
		if (strpos($className, 'Entity') !== false) {
			return $this->entityManager->getRepository($className);
		}

		return $this->documentManager->getRepository($className);
	}

	/**
	 * @param string $className
	 */
	public function getClassMetadata($className): ClassMetadata
	{
		if (strpos($className, 'Entity') !== false) {
			return $this->entityManager->getClassMetadata($className);
		}

		return $this->documentManager->getClassMetadata($className);
	}

	public function getMetadataFactory(): ClassMetadataFactory
	{
		return $this->classMetadataFactory;
	}

	/**
	 * @param string $className
	 * @param mixed  $id
	 */
	public function find($className, $id): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function persist($object): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function remove($object): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function merge($object): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param string|null $objectName
	 */
	public function clear($objectName = null): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function detach($object): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function refresh($object): void
	{
		throw new \Exception(__FILE__);
	}

	public function flush(): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function initializeObject($object): void
	{
		throw new \Exception(__FILE__);
	}

	/**
	 * @param object $object
	 */
	public function contains($object): bool
	{
		throw new \Exception(__FILE__);
	}

};
