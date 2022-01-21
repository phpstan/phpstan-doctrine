<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use PHPStan\Reflection\ReflectionProvider;
use function is_file;
use function is_readable;

final class ObjectMetadataResolver
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var string|null */
	private $objectManagerLoader;

	/** @var ObjectManager|null|false */
	private $objectManager;

	/** @var string|null */
	private $repositoryClass;

	/** @var string|null */
	private $resolvedRepositoryClass;

	/** @var ClassMetadataFactory<ClassMetadata>|null */
	private $metadataFactory;

	public function __construct(
		ReflectionProvider $reflectionProvider,
		?string $objectManagerLoader,
		?string $repositoryClass
	)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->repositoryClass = $repositoryClass;
	}

	/** @api */
	public function getObjectManager(): ?ObjectManager
	{
		if ($this->objectManager === false) {
			return null;
		}

		if ($this->objectManager !== null) {
			return $this->objectManager;
		}

		if ($this->objectManagerLoader === null) {
			$this->objectManager = false;

			return null;
		}

		$this->objectManager = $this->loadObjectManager($this->objectManagerLoader);

		return $this->objectManager;
	}

	/**
	 * @param class-string $className
	 * @return bool
	 */
	public function isTransient(string $className): bool
	{
		$objectManager = $this->getObjectManager();

		try {
			if ($objectManager === null) {
				return $this->getMetadataFactory()->isTransient($className);
			}

			return $objectManager->getMetadataFactory()->isTransient($className);
		} catch (\ReflectionException $e) {
			return true;
		}
	}

	/**
	 * @return ClassMetadataFactory<ClassMetadata>
	 */
	private function getMetadataFactory(): ClassMetadataFactory
	{
		if ($this->metadataFactory !== null) {
			return $this->metadataFactory;
		}

		$metadataFactory = new \PHPStan\Doctrine\Mapping\ClassMetadataFactory();

		return $this->metadataFactory = $metadataFactory;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $className
	 * @return ClassMetadataInfo<T>|null
	 */
	public function getClassMetadata(string $className): ?ClassMetadataInfo
	{
		if ($this->isTransient($className)) {
			return null;
		}

		$objectManager = $this->getObjectManager();

		try {
			if ($objectManager === null) {
				$metadata = $this->getMetadataFactory()->getMetadataFor($className);
			} else {
				$metadata = $objectManager->getClassMetadata($className);
			}
		} catch (\Doctrine\ORM\Mapping\MappingException $e) {
			return null;
		}

		if (!$metadata instanceof ClassMetadataInfo) {
			return null;
		}

		/** @var \Doctrine\ORM\Mapping\ClassMetadataInfo<T> $ormMetadata */
		$ormMetadata = $metadata;

		return $ormMetadata;
	}

	private function loadObjectManager(string $objectManagerLoader): ?ObjectManager
	{
		if (!is_file($objectManagerLoader)) {
			throw new \PHPStan\ShouldNotHappenException(sprintf(
				'Object manager could not be loaded: file "%s" does not exist',
				$objectManagerLoader
			));
		}

		if (!is_readable($objectManagerLoader)) {
			throw new \PHPStan\ShouldNotHappenException(sprintf(
				'Object manager could not be loaded: file "%s" is not readable',
				$objectManagerLoader
			));
		}

		return require $objectManagerLoader;
	}

	public function getResolvedRepositoryClass(): string
	{
		if ($this->resolvedRepositoryClass !== null) {
			return $this->resolvedRepositoryClass;
		}

		$objectManager = $this->getObjectManager();
		if ($this->repositoryClass !== null) {
			return $this->resolvedRepositoryClass = $this->repositoryClass;
		}

		if ($objectManager !== null && get_class($objectManager) === 'Doctrine\ODM\MongoDB\DocumentManager') {
			return $this->resolvedRepositoryClass = 'Doctrine\ODM\MongoDB\Repository\DocumentRepository';
		}

		return $this->resolvedRepositoryClass = 'Doctrine\ORM\EntityRepository';
	}

	public function getRepositoryClass(string $className): string
	{
		if (!$this->reflectionProvider->hasClass($className)) {
			return $this->getResolvedRepositoryClass();
		}

		$classReflection = $this->reflectionProvider->getClass($className);
		if ($classReflection->isInterface() || $classReflection->isTrait()) {
			return $this->getResolvedRepositoryClass();
		}

		$metadata = $this->getClassMetadata($classReflection->getName());
		if ($metadata !== null) {
			return $metadata->customRepositoryClassName ?? $this->getResolvedRepositoryClass();
		}

		$objectManager = $this->getObjectManager();
		if ($objectManager === null) {
			return $this->getResolvedRepositoryClass();
		}

		$metadata = $objectManager->getClassMetadata($classReflection->getName());
		$odmMetadataClass = 'Doctrine\ODM\MongoDB\Mapping\ClassMetadata';
		if ($metadata instanceof $odmMetadataClass) {
			/** @var \Doctrine\ODM\MongoDB\Mapping\ClassMetadata<object> $odmMetadata */
			$odmMetadata = $metadata;
			return $odmMetadata->customRepositoryClassName ?? $this->getResolvedRepositoryClass();
		}

		return $this->getResolvedRepositoryClass();
	}

}
