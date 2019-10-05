<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManager;

class RepositoryFindByCalls
{

	/** @var EntityManager */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function doFindBy(): void
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$entityRepository->findBy(['id' => 1]);
		$entityRepository->findBy(['title' => 'test']);
		$entityRepository->findBy(['transient' => 'test']);
		$entityRepository->findBy(['nonexistent' => 'test']);
		$entityRepository->findBy(['nonexistent' => 'test', 'transient' => 'test']);
	}

	public function doFindOneBy(): void
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$entityRepository->findOneBy(['id' => 1]);
		$entityRepository->findOneBy(['title' => 'test']);
		$entityRepository->findOneBy(['transient' => 'test']);
		$entityRepository->findOneBy(['nonexistent' => 'test']);
		$entityRepository->findOneBy(['nonexistent' => 'test', 'transient' => 'test']);
	}

	public function doCountBy(): void
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$entityRepository->count(['id' => 1]);
		$entityRepository->count(['title' => 'test']);
		$entityRepository->count(['transient' => 'test']);
		$entityRepository->count(['nonexistent' => 'test']);
		$entityRepository->count(['nonexistent' => 'test', 'transient' => 'test']);
	}

}
