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
		$entityRepository = $this->entityManager->getRepository(MyEntityImplementingInterface::class);
		$entityRepository->findBy(['id' => 1]);
		$entityRepository->findBy(['nonexistent' => 'test']);
	}

}
