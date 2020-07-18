<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\EntityRepositoryDynamicReturn;

use Doctrine\ORM\EntityManager;
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
		$test->doSomethingElse();
	}

	public function findOneByDynamicType(): void
	{
		$test = $this->repository->findOneBy(['blah' => 'testing']);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findAllDynamicType(): void
	{
		$items = $this->repository->findAll();

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}

	public function findByDynamicType(): void
	{
		$items = $this->repository->findBy(['blah' => 'testing']);

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}
}

class Example2
{
	/**
	 * @var EntityRepository<MyEntity>
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
		$test->doSomethingElse();
	}

	public function findOneByDynamicType(): void
	{
		$test = $this->repository->findOneBy(['blah' => 'testing']);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findAllDynamicType(): void
	{
		$items = $this->repository->findAll();

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}

	public function findByDynamicType(): void
	{
		$items = $this->repository->findBy(['blah' => 'testing']);

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}
}

class Example3MagicMethods
{
	/**
	 * @var EntityRepository<MyEntity>
	 */
	private $repository;

	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->repository = $entityManager->getRepository(MyEntity::class);
	}

	public function findDynamicType(): void
	{
		$test = $this->repository->findById(1);
		foreach ($test as $item) {
			$item->doSomething();
			$item->doSomethingElse();
		}
	}

	public function findDynamicType2(): void
	{
		$test = $this->repository->findByNonexistent(1);
	}

	public function findOneByDynamicType(): void
	{
		$test = $this->repository->findOneById(1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findOneDynamicType2(): void
	{
		$test = $this->repository->findOneByNonexistent(1);
	}

	public function countBy(): void
	{
		$test = $this->repository->countById(1);
		if ($test === 'foo') {

		}
		$test = $this->repository->countByNonexistent('test');
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

interface EntityInterface
{

}

class GetRepositoryOnNonClasses
{

	public function doFoo(EntityManagerInterface $entityManager): void
	{
		$entityManager->getRepository(EntityInterface::class);
	}

	public function doBar(EntityManagerInterface $entityManager): void
	{
		$entityManager->getRepository('nonexistentClass');
	}

}

abstract class BaseEntity
{

	/**
	 * @return EntityRepository<self>
	 */
	public function getRepository(): EntityRepository
	{
		return $this->getEntityManager()->getRepository(static::class);
	}

	abstract public function getEntityManager(): EntityManager;

}
