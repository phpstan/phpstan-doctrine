<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\QueryBuilder;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use PHPStan\DoctrineIntegration\ORM\CustomRepositoryUsage\MyEntity;

class Foo
{

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function doFoo(): Query
	{
		$queryBuilder = $this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e');
		$query = $queryBuilder->getQuery();

		$query->getDQL() === 'aaa';
		$queryBuilder->getDQL() === 'aaa';

		return $query;
	}

	public function doBar(): Query
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$queryBuilder = $entityRepository->createQueryBuilder('e');
		$query = $queryBuilder->getQuery();

		$query->getDQL() === 'bbb';
		$queryBuilder->getDQL() === 'bbb';

		return $query;
	}

	/**
	 * @phpstan-param class-string $entityClass
	 */
	public function dynamicQueryBuilder(string $entityClass): Query
	{
		$entityRepository = $this->entityManager->getRepository($entityClass);
		$queryBuilder = $entityRepository->createQueryBuilder('e');
		return $queryBuilder->getQuery();
	}

	public function usingMethodThatReturnStatic(): ?MyEntity
	{
		$queryBuilder = $this->entityManager->createQueryBuilder();

		$queryBuilder
			->select('e')
			->from(MyEntity::class, 'e')
			->where('e.id = :id')
			->setParameters([
				'id' => 123,
			]);

		return $queryBuilder->getQuery()->getOneOrNullResult();
	}
}
