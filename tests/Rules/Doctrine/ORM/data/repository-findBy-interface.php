<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManager;

class RepositoryForInterfaceFindByCalls
{

	/** @var EntityManager */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function doFindByWithConcreteClass(): void
	{
		$entityRepository = $this->entityManager->getRepository(MyEntityImplementingInterface::class);
		$entityRepository->findBy(['id' => 1]);
		$entityRepository->findBy(['nonexistent' => 'test']);
	}

	public function doFindByWithInterface(MyInterface $entity): void
	{
		$entityRepository = $this->entityManager->getRepository(get_class($entity));
		// This is likely to work, but cannot be inferred from the interface
		// alone
		$repo->findBy(['name' => $entity->getName()]);
		// This is NOT likely to work, but can't be proven without examining
		// all implementations of the interface
		$entityRepository->findBy(['nonexistent' => 'test']);
	}

}
