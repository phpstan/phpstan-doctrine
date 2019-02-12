<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use RuntimeException;
use function file_exists;
use function is_readable;

final class ObjectMetadataResolver
{

	/** @var ?ObjectManager */
	private $objectManager;

	/** @var string */
	private $repositoryClass;

	public function __construct(?string $objectManagerLoader, ?string $repositoryClass)
	{
		if ($objectManagerLoader !== null) {
			$this->objectManager = $this->getObjectManager($objectManagerLoader);
		}
		if ($repositoryClass !== null) {
			$this->repositoryClass = $repositoryClass;
		} elseif ($this->objectManager !== null && get_class($this->objectManager) === 'Doctrine\ODM\MongoDB\DocumentManager') {
			$this->repositoryClass = 'Doctrine\ODM\MongoDB\DocumentRepository';
		} else {
			$this->repositoryClass = 'Doctrine\ORM\EntityRepository';
		}
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint
	 * @param string $objectManagerLoader
	 * @return ObjectManager
	 */
	private function getObjectManager(string $objectManagerLoader)
	{
		if (! file_exists($objectManagerLoader) && ! is_readable($objectManagerLoader)) {
			throw new \PHPStan\ShouldNotHappenException('Object manager could not be loaded');
		}

		return require $objectManagerLoader;
	}

	public function getRepositoryClass(string $className): string
	{
		if ($this->objectManager === null) {
			return $this->repositoryClass;
		}

		$metadata = $this->objectManager->getClassMetadata($className);

		$ormMetadataClass = 'Doctrine\ORM\Mapping\ClassMetadata';
		if ($metadata instanceof $ormMetadataClass) {
			/** @var \Doctrine\ORM\Mapping\ClassMetadata $ormMetadata */
			$ormMetadata = $metadata;
			return $ormMetadata->customRepositoryClassName ?? $this->repositoryClass;
		}

		$odmMetadataClass = 'Doctrine\ODM\MongoDB\Mapping\ClassMetadata';
		if ($metadata instanceof $odmMetadataClass) {
			/** @var \Doctrine\ODM\MongoDB\Mapping\ClassMetadata $odmMetadata */
			$odmMetadata = $metadata;
			return $odmMetadata->customRepositoryClassName ?? $this->repositoryClass;
		}

		return $this->repositoryClass;
	}

}
