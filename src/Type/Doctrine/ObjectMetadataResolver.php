<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\ObjectManager;
use PHPStan\Doctrine\Mapping\ClassMetadataFactory;
use PHPStan\ShouldNotHappenException;
use ReflectionException;
use function class_exists;
use function is_file;
use function is_readable;
use function method_exists;
use function sprintf;
use const PHP_VERSION_ID;

final class ObjectMetadataResolver
{

	private ?string $objectManagerLoader = null;

	/** @var ObjectManager|false|null */
	private $objectManager;

	private ?ClassMetadataFactory $metadataFactory = null;

	private string $tmpDir;

	public function __construct(
		?string $objectManagerLoader,
		string $tmpDir
	)
	{
		$this->objectManagerLoader = $objectManagerLoader;
		$this->tmpDir = $tmpDir;
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

	public function isNativeLazyObjectsEnabled(): bool
	{
		$objectManager = $this->getObjectManager();

		if ($objectManager instanceof EntityManagerInterface) {
			$config = $objectManager->getConfiguration();

			// @phpstan-ignore function.impossibleType, function.alreadyNarrowedType (Available since Doctrine ORM 3.4)
			if (method_exists($config, 'isNativeLazyObjectsEnabled') && $config->isNativeLazyObjectsEnabled()) {
				return true;
			}

			return false;
		}

		// No object manager - check if the standalone ClassMetadataFactory would enable native lazy objects
		// @phpstan-ignore function.impossibleType, function.alreadyNarrowedType (Available since Doctrine ORM 3.4)
		if (PHP_VERSION_ID >= 80400 && class_exists(Configuration::class) && method_exists(Configuration::class, 'enableNativeLazyObjects')) {
			return true;
		}

		return false;
	}

	/**
	 * @param class-string $className
	 */
	public function isTransient(string $className): bool
	{
		if (!class_exists($className)) {
			return true;
		}

		$objectManager = $this->getObjectManager();

		try {
			if ($objectManager === null) {
				$metadataFactory = $this->getMetadataFactory();
				if ($metadataFactory === null) {
					return true;
				}

				return $metadataFactory->isTransient($className);
			}

			return $objectManager->getMetadataFactory()->isTransient($className);
		} catch (ReflectionException $e) {
			return true;
		}
	}

	private function getMetadataFactory(): ?ClassMetadataFactory
	{
		if ($this->metadataFactory !== null) {
			return $this->metadataFactory;
		}

		if (!class_exists(\Doctrine\ORM\Mapping\ClassMetadataFactory::class)) {
			return null;
		}

		return $this->metadataFactory = new ClassMetadataFactory($this->tmpDir);
	}

	/**
	 * @api
	 *
	 * @template T of object
	 * @param class-string<T> $className
	 * @return ClassMetadata<T>|null
	 */
	public function getClassMetadata(string $className): ?ClassMetadata
	{
		if ($this->isTransient($className)) {
			return null;
		}

		$objectManager = $this->getObjectManager();

		try {
			if ($objectManager === null) {
				$metadataFactory = $this->getMetadataFactory();
				if ($metadataFactory === null) {
					return null;
				}

				/** @throws \Doctrine\Persistence\Mapping\MappingException | MappingException | AnnotationException */
				$metadata = $metadataFactory->getMetadataFor($className);
			} else {
				/** @throws \Doctrine\Persistence\Mapping\MappingException | MappingException | AnnotationException */
				$metadata = $objectManager->getClassMetadata($className);
			}
		} catch (\Doctrine\Persistence\Mapping\MappingException | MappingException | AnnotationException $e) {
			return null;
		}

		if (!$metadata instanceof ClassMetadata) {
			return null;
		}

		/** @var ClassMetadata<T> $ormMetadata */
		$ormMetadata = $metadata;

		return $ormMetadata;
	}

	private function loadObjectManager(string $objectManagerLoader): ?ObjectManager
	{
		if (!is_file($objectManagerLoader)) {
			throw new ShouldNotHappenException(sprintf(
				'Object manager could not be loaded: file "%s" does not exist',
				$objectManagerLoader,
			));
		}

		if (!is_readable($objectManagerLoader)) {
			throw new ShouldNotHappenException(sprintf(
				'Object manager could not be loaded: file "%s" is not readable',
				$objectManagerLoader,
			));
		}

		return require $objectManagerLoader;
	}

}
