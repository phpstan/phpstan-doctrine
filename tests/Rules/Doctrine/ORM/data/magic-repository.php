<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManager;

class MagicRepositoryCalls
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
		$entityRepository->findById(1);
		$entityRepository->findByTitle('test');
		$entityRepository->findByTransient('test');
		$entityRepository->findByNonexistent('test');
	}

	public function doFindOneBy(): void
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$entityRepository->findOneBy(['id' => 1]);
		$entityRepository->findOneById(1);
		$entityRepository->findOneByTitle('test');
		$entityRepository->findOneByTransient('test');
		$entityRepository->findOneByNonexistent('test');
	}

	public function doCountBy(): void
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$entityRepository->countBy(['id' => 1]);
		$entityRepository->countById(1);
		$entityRepository->countByTitle('test');
		$entityRepository->countByTransient('test');
		$entityRepository->countByNonexistent('test');
	}

}
