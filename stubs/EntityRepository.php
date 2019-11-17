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
	 * @param mixed $id
	 * @param int|null $lockMode
	 * @param int|null $lockVersion
	 * @return TEntityClass|null
	 */
	public function find($id, $lockMode = null, $lockVersion = null);

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
	 * @param array|null $orderBy
	 * @return TEntityClass|null
	 */
	public function findOneBy(array $criteria, array $orderBy = null);

	/**
	 * @return class-string<TEntityClass>
	 */
	public function getClassName();

	/**
	 * @return class-string<TEntityClass>
	 */
	protected function getEntityName();

}
