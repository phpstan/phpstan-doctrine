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
		$query = $this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->getQuery();

		$query->getDQL() === 'aaa';

		return $query;
	}

}
