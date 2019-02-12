<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata as ODMMetadata;
use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
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

	private function getObjectManager(string $objectManagerLoader): ObjectManager
	{
		if (! file_exists($objectManagerLoader) && ! is_readable($objectManagerLoader)) {
			throw new RuntimeException('Object manager could not be loaded');
		}

		return require $objectManagerLoader;
	}

	public function getRepositoryClass(string $className): string
	{
		if ($this->objectManager === null) {
			return $this->repositoryClass;
		}

		$metadata = $this->objectManager->getClassMetadata($className);

		if ($metadata instanceof ORMMetadata) {
			return $metadata->customRepositoryClassName ?? $this->repositoryClass;
		}

		if ($metadata instanceof ODMMetadata) {
			return $metadata->customRepositoryClassName ?? $this->repositoryClass;
		}

		return $this->repositoryClass;
	}

}
