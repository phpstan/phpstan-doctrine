<?php declare(strict_types = 1);

namespace PHPStan\Doctrine\Mapping;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\ReflectionService;
use PHPStan\Reflection\ReflectionProvider;
use ReflectionClass;
use function class_exists;
use function count;
use const PHP_VERSION_ID;

class ClassMetadataFactory extends \Doctrine\ORM\Mapping\ClassMetadataFactory
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var bool */
	private $bleedingEdge;

	/** @var BetterReflectionService|null */
	private $reflectionService = null;

	public function __construct(ReflectionProvider $reflectionProvider, bool $bleedingEdge)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->bleedingEdge = $bleedingEdge;
	}

	protected function initialize(): void
	{
		$parentReflection = new ReflectionClass(parent::class);
		$driverProperty = $parentReflection->getProperty('driver');
		$driverProperty->setAccessible(true);

		$drivers = [];
		if (class_exists(AnnotationReader::class)) {
			$docParser = new DocParser();
			$docParser->setIgnoreNotImportedAnnotations(true);
			$drivers[] = new AnnotationDriver(new AnnotationReader($docParser));
		}
		if (class_exists(AttributeDriver::class) && PHP_VERSION_ID >= 80000) {
			$drivers[] = new AttributeDriver([]);
		}

		$driverProperty->setValue($this, count($drivers) === 1 ? $drivers[0] : new MappingDriverChain($drivers));

		$evmProperty = $parentReflection->getProperty('evm');
		$evmProperty->setAccessible(true);
		$evmProperty->setValue($this, new EventManager());
		$this->initialized = true;

		$targetPlatformProperty = $parentReflection->getProperty('targetPlatform');
		$targetPlatformProperty->setAccessible(true);

		if (class_exists(MySqlPlatform::class)) {
			$platform = new MySqlPlatform();
		} else {
			$platform = new \Doctrine\DBAL\Platforms\MySQLPlatform();
		}

		$targetPlatformProperty->setValue($this, $platform);
	}

	public function getReflectionService(): ReflectionService
	{
		if (!$this->bleedingEdge) {
			return parent::getReflectionService();
		}

		if ($this->reflectionService === null) {
			$this->reflectionService = new BetterReflectionService($this->reflectionProvider);
		}

		return $this->reflectionService;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return ClassMetadata<T>
	 */
	protected function newClassMetadataInstance($className)
	{
		return new ClassMetadata($className);
	}

}
