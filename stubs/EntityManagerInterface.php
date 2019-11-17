<?php

namespace Doctrine\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

interface EntityManagerInterface extends ObjectManager
{

	/**
	 * @template T
	 * @param class-string<T> $className
	 * @param mixed  $id
	 * @return T|null
	 */
	public function find($className, $id);

	/**
	 * @template T
	 * @param T $object
	 * @return T
	 */
	public function merge($object);

	/**
	 * @template T
	 * @param class-string<T> $className
	 * @return ObjectRepository<T>
	 */
	public function getRepository($className);

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
