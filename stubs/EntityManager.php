<?php

namespace Doctrine\ORM;

class EntityManager implements EntityManagerInterface
{

	/**
	 * @template T
	 * @param class-string<T> $entityName
	 * @param mixed  $id
	 * @param integer|null $lockMode
	 * @param integer|null $lockVersion
	 * @return T|null
	 */
	public function find($entityName, $id, $lockMode = null, $lockVersion = null);

	/**
	 * @template T
	 * @param T $entity
	 * @return T
	 */
	public function merge($entity);

	/**
	 * @template T
	 * @param class-string<T> $entityName
	 * @return EntityRepository<T>
	 */
	public function getRepository($entityName);

	/**
	 * @template T
	 * @param class-string<T> $entityName
	 * @param mixed $id
	 * @return T|null
	 */
	public function getReference($entityName, $id);

	/**
	 * @template T
	 * @param class-string<T> $entityName
	 * @param mixed $identifier
	 *
	 * @return T|null
	 */
	public function getPartialReference($entityName, $identifier);

	/**
	 * @template T
	 * @param T $entity
	 * @param bool $deep
	 * @return T
	 */
	public function copy($entity, $deep = false);

}
