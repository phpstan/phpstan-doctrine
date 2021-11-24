<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

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
		$objectManager = $this->getObjectManager();
		if ($objectManager === null) {
			return $this->getResolvedRepositoryClass();
		}

		if (!$this->reflectionProvider->hasClass($className)) {
			return $this->getResolvedRepositoryClass();
		}

		$classReflection = $this->reflectionProvider->getClass($className);
		if ($classReflection->isInterface() || $classReflection->isTrait()) {
			return $this->getResolvedRepositoryClass();
		}

		$metadata = $objectManager->getClassMetadata($classReflection->getName());

		$ormMetadataClass = 'Doctrine\ORM\Mapping\ClassMetadata';
		if ($metadata instanceof $ormMetadataClass) {
			/** @var \Doctrine\ORM\Mapping\ClassMetadata $ormMetadata */
			$ormMetadata = $metadata;
			return $ormMetadata->customRepositoryClassName ?? $this->getResolvedRepositoryClass();
		}

		$odmMetadataClass = 'Doctrine\ODM\MongoDB\Mapping\ClassMetadata';
		if ($metadata instanceof $odmMetadataClass) {
			/** @var \Doctrine\ODM\MongoDB\Mapping\ClassMetadata $odmMetadata */
			$odmMetadata = $metadata;
			return $odmMetadata->customRepositoryClassName ?? $this->getResolvedRepositoryClass();
		}

		return $this->getResolvedRepositoryClass();
	}

}
