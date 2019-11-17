<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\CustomRepositoryUsage;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

class Example
{
	/**
	 * @var MyRepository
	 */
	private $repository;

	/**
	 * @var MyRepository<MyEntity>
	 */
	private $anotherRepository;

	public function __construct(
		EntityManagerInterface $entityManager,
		MyRepository $anotherRepository
	)
	{
		$this->repository = $entityManager->getRepository(MyEntity::class);
		$this->anotherRepository = $anotherRepository;
	}

	public function get(): void
	{
		$test = $this->repository->get(1);
		$test->doSomethingElse();
		$test->nonexistent();
	}

	public function nonexistant(): void
	{
		$this->repository->nonexistant();
	}

	public function nonGenericRepository(): void
	{
		$entity = $this->repository->find(1);
		$entity->doSomethingElse();
		$entity->nonexistent();
	}

	public function genericRepository(): void
	{
		$entity = $this->anotherRepository->find(1);
		$entity->doSomethingElse();
		$entity->nonexistent();
	}

	public function callExistingMethodOnRepository(): void
	{
		$this->repository->findOneByBlabla()->test();
		$this->anotherRepository->findOneByBlabla()->test();
	}
}

/**
 * @ORM\Entity(repositoryClass=MyRepository::class)
 */
class MyEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;

	public function doSomethingElse(): void
	{
	}
}

/**
 * @template T
 * @extends EntityRepository<T>
 */
class MyRepository extends EntityRepository
{
	public function get(int $id): MyEntity
	{
		$entity = $this->find($id);

		if ($entity === null) {
			throw new RuntimeException('Not found...');
		}

		return $entity;
	}

	public function findOneByBlabla(): int
	{
		return 1;
	}
}
