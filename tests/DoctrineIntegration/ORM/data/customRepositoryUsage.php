<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\CustomRepositoryUsage;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
use function PHPStan\Testing\assertType;

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
		assertType(MyEntity::class, $test);
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
		assertType('object|null', $entity);
		$entity->doSomethingElse();
		$entity->nonexistent();
	}

	public function genericRepository(): void
	{
		$entity = $this->anotherRepository->find(1);
		assertType(MyEntity::class . '|null', $entity);
		$entity->doSomethingElse();
		$entity->nonexistent();
	}

	public function callExistingMethodOnRepository(): void
	{
		assertType('int', $this->repository->findOneByBlabla());
		assertType('int', $this->anotherRepository->findOneByBlabla());
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
 * @template T of object
 * @extends EntityRepository<T>
 */
class MyRepository extends EntityRepository
{
	public function get(int $id): MyEntity
	{
		$entity = $this->find($id);
		assertType('T of object (class PHPStan\DoctrineIntegration\ORM\CustomRepositoryUsage\MyRepository, argument)|null', $entity);

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

/**
 * @ORM\Entity(repositoryClass=NonGenericRepository::class)
 */
class EntityWithNonGenericRepository
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;
}

/**
 * @extends EntityRepository<EntityWithNonGenericRepository>
 */
class NonGenericRepository extends EntityRepository
{

}

/**
 * @ORM\Entity(repositoryClass=UnrelatedTemplateRepository::class)
 */
class EntityWithUnrelatedTemplateRepository
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;
}

/**
 * @template TKey
 * @extends EntityRepository<EntityWithUnrelatedTemplateRepository>
 */
class UnrelatedTemplateRepository extends EntityRepository
{

}

/**
 * @ORM\Entity(repositoryClass=TwoTemplatesRepository::class)
 */
class EntityWithTwoTemplatesRepository
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;
}

/**
 * @template T of object
 * @template TKey
 * @extends EntityRepository<T>
 */
class TwoTemplatesRepository extends EntityRepository
{

}

class UseNonGenericRepository
{

	public function doFoo(EntityManagerInterface $entityManager): void
	{
		$repository = $entityManager->getRepository(EntityWithNonGenericRepository::class);
		assertType(NonGenericRepository::class, $repository);
		assertType(EntityWithNonGenericRepository::class . '|null', $repository->find(1));

		$repository = $entityManager->getRepository(EntityWithUnrelatedTemplateRepository::class);
		assertType(UnrelatedTemplateRepository::class, $repository);
		assertType(EntityWithUnrelatedTemplateRepository::class . '|null', $repository->find(1));

		assertType(TwoTemplatesRepository::class, $entityManager->getRepository(EntityWithTwoTemplatesRepository::class));
		assertType(MyRepository::class . '<' . MyEntity::class . '>', $entityManager->getRepository(MyEntity::class));
	}

}

/**
 * @template T of MyEntity
 * @extends EntityRepository<T>
 */
class AbstractRepository extends EntityRepository
{

}

class UseAbstractRepository
{

	/** @var AbstractRepository */
	private $repository;

	/** @var AbstractRepository<MyEntity> */
	private $genericRepository;

	/**
	 * @param AbstractRepository<MyEntity> $genericRepository
	 */
	public function __construct(
		AbstractRepository $repository,
		AbstractRepository $genericRepository
	)
	{
		$this->repository = $repository;
		$this->genericRepository = $genericRepository;
	}

	public function find(): void
	{
		$entity = $this->repository->findOneById(1);
		$entity = $this->genericRepository->findOneById(1);
	}

}
