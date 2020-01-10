<?php

namespace Doctrine\Common\Persistence;

/**
 * @template TEntityClass
 */
interface ObjectRepository
{

	/**
	 * @phpstan-param mixed $id
	 * @phpstan-return TEntityClass|null
	 */
	public function find($id);

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
	 * @phpstan-return TEntityClass|null
	 */
	public function findOneBy(array $criteria);

	/**
	 * @phpstan-return class-string<TEntityClass>
	 */
	public function getClassName();

}
