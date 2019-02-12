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

	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->repository = $entityManager->getRepository(MyEntity::class);
	}

	public function get(): void
	{
		$test = $this->repository->get(1);
		$test->doSomethingElse();
	}

	public function nonexistant(): void
	{
		$this->repository->nonexistant();
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
}
