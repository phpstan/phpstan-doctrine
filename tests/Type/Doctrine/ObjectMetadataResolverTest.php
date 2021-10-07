<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Type\Doctrine\CustomObjectManager\MyDocument;
use PHPStan\Type\Doctrine\CustomObjectManager\MyEntity;

final class ObjectMetadataResolverTest extends \PHPStan\Testing\PHPStanTestCase
{

	public function testGetRepositoryClassWithCustomObjectManager(): void
	{
		$objectMetadataResolver = new ObjectMetadataResolver(
			$this->createReflectionProvider(),
			__DIR__ . '/data/CustomObjectManager/custom-object-manager.php',
			null
		);

		self::assertSame('Doctrine\ODM\MongoDB\Repository\DocumentRepository', $objectMetadataResolver->getRepositoryClass(MyDocument::class));
		self::assertSame('Doctrine\ORM\EntityRepository', $objectMetadataResolver->getRepositoryClass(MyEntity::class));
	}

}
