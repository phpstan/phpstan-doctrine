<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\EntityRepositoryDynamicReturn;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

class Example
{
	/**
	 * @var EntityRepository
	 */
	private $repository;

	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->repository = $entityManager->getRepository(MyEntity::class);
	}

	public function findDynamicType(): void
	{
		$test = $this->repository->find(1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
	}

	public function findOneByDynamicType(): void
	{
		$test = $this->repository->findOneBy(['blah' => 'testing']);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
	}

	public function findAllDynamicType(): void
	{
		$items = $this->repository->findAll();

		foreach ($items as $test) {
			$test->doSomething();
		}
	}

	public function findByDynamicType(): void
	{
		$items = $this->repository->findBy(['blah' => 'testing']);

		foreach ($items as $test) {
			$test->doSomething();
		}
	}
}

/**
 * @ORM\Entity()
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

	public function doSomething(): void
	{
	}
}
