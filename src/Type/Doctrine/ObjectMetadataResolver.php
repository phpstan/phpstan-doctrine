<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPStan\Doctrine\Mapping\ClassMetadataFactory;
use PHPStan\ShouldNotHappenException;
use ReflectionException;
use Throwable;
use function array_merge;
use function class_exists;
use function count;
use function is_file;
use function is_readable;
use function method_exists;
use function preg_match_all;
use function preg_replace;
use function reset;
use function sprintf;
use const PHP_VERSION_ID;

final class ObjectMetadataResolver
{

	private ?string $objectManagerLoader = null;

	/** @var ObjectManager|ManagerRegistry|false|null */
	private $objectManagerLoaderResult;

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
		$objectManagerLoaderResult = $this->getObjectManagerLoaderResult();
		if (!$objectManagerLoaderResult instanceof ManagerRegistry) {
			return $objectManagerLoaderResult;
		}

		return $objectManagerLoaderResult->getManager();
	}

	/**
	 * @param class-string $className
	 */
	public function getObjectManagerForClass(string $className): ?ObjectManager
	{
		$objectManagerLoaderResult = $this->getObjectManagerLoaderResult();
		if (!$objectManagerLoaderResult instanceof ManagerRegistry) {
			return $objectManagerLoaderResult;
		}

		$objectManager = $objectManagerLoaderResult->getManagerForClass($className);
		if ($objectManager instanceof ObjectManager) {
			return $objectManager;
		}

		return $this->getObjectManager();
	}

	public function getObjectManagerByName(string $name): ?ObjectManager
	{
		$objectManagerLoaderResult = $this->getObjectManagerLoaderResult();
		if (!$objectManagerLoaderResult instanceof ManagerRegistry) {
			return null;
		}

		try {
			return $objectManagerLoaderResult->getManager($name);
		} catch (Throwable $e) {
			return null;
		}
	}

	public function getObjectManagerForDql(string $dql): ?ObjectManager
	{
		$objectManagerLoaderResult = $this->getObjectManagerLoaderResult();
		if (!$objectManagerLoaderResult instanceof ManagerRegistry) {
			return $objectManagerLoaderResult;
		}

		$dqlWithoutStringLiterals = preg_replace("~'(?:''|[^'])*'~", "''", $dql);
		if ($dqlWithoutStringLiterals === null) {
			$dqlWithoutStringLiterals = $dql;
		}

		preg_match_all('~\b(?:FROM|UPDATE)\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)(?:\s+|$)~i', $dqlWithoutStringLiterals, $matches);
		preg_match_all('~\bDELETE\s+(?:FROM\s+)?([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)(?:\s+|$)~i', $dqlWithoutStringLiterals, $deleteMatches);
		foreach (array_merge($matches[1], $deleteMatches[1]) as $className) {
			if (!class_exists($className)) {
				continue;
			}

			$objectManager = $objectManagerLoaderResult->getManagerForClass($className);
			if ($objectManager !== null) {
				return $objectManager;
			}
		}

		return $this->getObjectManager();
	}

	/**
	 * @return ObjectManager|ManagerRegistry|null
	 */
	private function getObjectManagerLoaderResult()
	{
		if ($this->objectManagerLoaderResult === false) {
			return null;
		}

		if ($this->objectManagerLoaderResult !== null) {
			return $this->objectManagerLoaderResult;
		}

		if ($this->objectManagerLoader === null) {
			$this->objectManagerLoaderResult = false;

			return null;
		}

		$objectManagerLoaderResult = $this->loadObjectManager($this->objectManagerLoader);
		if ($objectManagerLoaderResult instanceof ManagerRegistry) {
			$objectManagers = $objectManagerLoaderResult->getManagers();
			if (count($objectManagers) === 1) {
				$objectManagerLoaderResult = reset($objectManagers);
			}
		}

		$this->objectManagerLoaderResult = $objectManagerLoaderResult;

		return $this->objectManagerLoaderResult;
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

		$objectManager = $this->getObjectManagerForClass($className);

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

		$objectManager = $this->getObjectManagerForClass($className);

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

	/**
	 * @return ObjectManager|ManagerRegistry|null
	 */
	private function loadObjectManager(string $objectManagerLoader)
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
