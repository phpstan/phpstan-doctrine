<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use function is_file;
use function is_readable;

final class ObjectMetadataResolver
{

	/** @var string|null */
	private $objectManagerLoader;

	/** @var ObjectManager|null|false */
	private $objectManager;

	/** @var ClassMetadataFactory<ClassMetadata>|null */
	private $metadataFactory;

	public function __construct(
		?string $objectManagerLoader,
	)
	{
		$this->objectManagerLoader = $objectManagerLoader;
	}

	public function hasObjectManagerLoader(): bool
	{
		return $this->objectManagerLoader !== null;
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

}
