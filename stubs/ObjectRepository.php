<?php

namespace Doctrine\Common\Persistence;

/**
 * @template TEntityClass
 */
interface ObjectRepository
{

	/**
	 * @param mixed $id
	 * @return TEntityClass|null
	 */
	public function find($id);

	/**
	 * @return TEntityClass[]
	 */
	public function findAll();

	/**
	 * @param mixed[] $criteria
	 * @param string[]|null $orderBy
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return TEntityClass[]
	 */
	public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null);

	/**
	 * @param mixed[] $criteria The criteria.
	 * @return TEntityClass|null
	 */
	public function findOneBy(array $criteria);

	/**
	 * @return class-string<TEntityClass>
	 */
	public function getClassName();

}
