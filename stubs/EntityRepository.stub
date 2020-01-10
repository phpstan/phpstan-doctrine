<?php

namespace Doctrine\ORM;

use Doctrine\Common\Persistence\ObjectRepository;

/**
 * @template TEntityClass
 * @implements ObjectRepository<TEntityClass>
 */
class EntityRepository implements ObjectRepository
{

	/**
	 * @phpstan-return class-string<TEntityClass>
	 */
	protected function getEntityName();

	// following methods could be removed if we only supported doctrine/persistence ^1.3 because of different implemented ObjectRepository namespace

	/**
	 * @phpstan-param mixed $id
	 * @phpstan-param int|null $lockMode
	 * @phpstan-param int|null $lockVersion
	 * @phpstan-return TEntityClass|null
	 */
	public function find($id, $lockMode = null, $lockVersion = null);

	/**
	 * @phpstan-return TEntityClass[]
	 */
	public function findAll();

	/**
	 * @phpstan-param mixed[] $criteria
	 * @phpstan-param string[]|null $orderBy
	 * @phpstan-param int|null $limit
	 * @phpstan-param int|null $offset
	 * @phpstan-return TEntityClass[]
	 */
	public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null);

	/**
	 * @phpstan-param mixed[] $criteria The criteria.
	 * @phpstan-param mixed[]|null $orderBy
	 * @phpstan-return TEntityClass|null
	 */
	public function findOneBy(array $criteria, array $orderBy = null);

	/**
	 * @phpstan-return class-string<TEntityClass>
	 */
	public function getClassName();

}
