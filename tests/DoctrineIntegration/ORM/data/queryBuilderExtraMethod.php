<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\QueryBuilder;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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

		// @todo it works when the extra where is here
		//$queryBuilder->andWhere('e.id != :excludedId');

		// but it does not work when the where is added in extra method
		$this->addExtraCondition($queryBuilder);

		$query = $queryBuilder->getQuery();

		$expectedDql = 'SELECT e FROM PHPStan\DoctrineIntegration\ORM\CustomRepositoryUsage\MyEntity e WHERE e.id != :excludedId';
		$query->getDQL() === $expectedDql;
		$queryBuilder->getDQL() === $expectedDql;

		return $query;
	}

	private function addExtraCondition(QueryBuilder $queryBuilder): void
	{
		$queryBuilder->andWhere('e.id != :excludedId');
	}

}
