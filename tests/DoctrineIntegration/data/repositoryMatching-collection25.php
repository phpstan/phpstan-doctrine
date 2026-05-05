<?php

namespace RepositoryMatching25;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use function PHPStan\Testing\assertType;

class Foo
{

	/** @var EntityManager */
	private $entityManager;

	public function doFoo(): void
	{
		$repository = $this->entityManager->getRepository(MyEntity::class);
		$criteria = Criteria::create();
		$results = $repository->matching($criteria);
		assertType('Doctrine\Common\Collections\AbstractLazyCollection<int, RepositoryMatching25\MyEntity>&Doctrine\Common\Collections\Selectable<int, RepositoryMatching25\MyEntity>', $results);
		foreach ($results as $result) {
			assertType(MyEntity::class, $result);
		}
	}

	/**
	 * @param EntityRepository<MyEntity> $repository
	 */
	public function withTypedRepository(EntityRepository $repository): void
	{
		$criteria = Criteria::create();
		$results = $repository->matching($criteria);
		assertType('Doctrine\Common\Collections\AbstractLazyCollection<int, RepositoryMatching25\MyEntity>&Doctrine\Common\Collections\Selectable<int, RepositoryMatching25\MyEntity>', $results);
		foreach ($results as $result) {
			assertType(MyEntity::class, $result);
		}
	}

}

class MyEntity
{

}
