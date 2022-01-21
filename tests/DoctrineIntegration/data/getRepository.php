<?php

namespace GetRepository;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use function PHPStan\Testing\assertType;

class Foo
{

	/** @var EntityManager */
	private $entityManager;

	public function doFoo(): void
	{
		assertType('Doctrine\ORM\EntityRepository<GetRepository\MyEntity>', $this->entityManager->getRepository(MyEntity::class));
	}

}

class Bar
{

	/** @var DocumentManager */
	private $documentManager;

	public function doFoo(): void
	{
		assertType('Doctrine\ODM\MongoDB\Repository\DocumentRepository<GetRepository\MyEntity>', $this->documentManager->getRepository(MyEntity::class));
	}

}
