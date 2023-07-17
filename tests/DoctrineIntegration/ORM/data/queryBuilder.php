<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\QueryBuilder;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use PHPStan\DoctrineIntegration\ORM\CustomRepositoryUsage\MyEntity;
use function PHPStan\Testing\assertType;

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
		assertType('\'SELECT e FROM PHPStan\\\\DoctrineIntegration\\\\ORM\\\\CustomRepositoryUsage\\\\MyEntity e\'', $query->getDQL());
		assertType('\'SELECT e FROM PHPStan\\\\DoctrineIntegration\\\\ORM\\\\CustomRepositoryUsage\\\\MyEntity e\'', $queryBuilder->getDQL());

		return $query;
	}

	public function doBar(): Query
	{
		$entityRepository = $this->entityManager->getRepository(MyEntity::class);
		$queryBuilder = $entityRepository->createQueryBuilder('e');
		$query = $queryBuilder->getQuery();

		assertType('\'SELECT e FROM PHPStan\\\\DoctrineIntegration\\\\ORM\\\\CustomRepositoryUsage\\\\MyEntity e\'', $query->getDQL());
		assertType('\'SELECT e FROM PHPStan\\\\DoctrineIntegration\\\\ORM\\\\CustomRepositoryUsage\\\\MyEntity e\'', $queryBuilder->getDQL());

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

		$result = $queryBuilder->getQuery()->getOneOrNullResult();
		assertType('mixed', $result);

		return $result;
	}

	public function getCustomQueryBuilder(): CustomQueryBuilder
	{
		assertType(CustomQueryBuilder::class, $this->entityManager->createQueryBuilder());
	    return $this->entityManager->createQueryBuilder();
	}
}

class CustomQueryBuilder extends \Doctrine\ORM\QueryBuilder
{
}
