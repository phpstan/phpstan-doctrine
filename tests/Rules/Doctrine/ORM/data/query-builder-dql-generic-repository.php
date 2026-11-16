<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of GenericRepositorySuperclass
 * @extends EntityRepository<T>
 */
abstract class AbstractGenericRepository extends EntityRepository
{

	protected function createSharedQueryBuilder(): QueryBuilder
	{
		return $this->createQueryBuilder('e')
			->andWhere('e.shared = :shared');
	}

	public function findShared(): void
	{
		$this->createSharedQueryBuilder()
			->andWhere('e.id = :id')
			->getQuery();
	}

}

/**
 * @extends AbstractGenericRepository<GenericRepositoryEntityA>
 */
class GenericRepositoryA extends AbstractGenericRepository
{

	public function findOnlyA(): void
	{
		$this->createSharedQueryBuilder()
			->andWhere('e.onlyA = :onlyA')
			->getQuery();
	}

}

/**
 * @extends AbstractGenericRepository<GenericRepositoryEntityB>
 */
class GenericRepositoryB extends AbstractGenericRepository
{

	public function findOnlyB(): void
	{
		$this->createSharedQueryBuilder()
			->andWhere('e.onlyB = :onlyB')
			->getQuery();
	}

}
