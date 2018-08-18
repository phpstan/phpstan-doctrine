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

	/** @var ObjectManager */
	private $objectManager;

	public function __construct(string $objectManagerLoader)
	{
		$this->objectManager = $this->getObjectManager($objectManagerLoader);
	}

	private function getObjectManager(string $objectManagerLoader): ObjectManager
	{
		if (! file_exists($objectManagerLoader) && ! is_readable($objectManagerLoader)) {
			throw new RuntimeException('Object manager could not be loaded');
		}

		return require $objectManagerLoader;
	}

	public function getRepositoryClass(string $className): ?string
	{
		$metadata = $this->objectManager->getClassMetadata($className);

		if ($metadata instanceof ORMMetadata) {
			return $metadata->customRepositoryClassName ?? 'Doctrine\ORM\EntityRepository';
		}

		if ($metadata instanceof ODMMetadata) {
			return $metadata->customRepositoryClassName ?? 'Doctrine\ODM\MongoDB\DocumentRepository';
		}

		return null;
	}

}
