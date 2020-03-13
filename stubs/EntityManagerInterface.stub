<?php

namespace Doctrine\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

interface EntityManagerInterface extends ObjectManager
{

	/**
	 * @template T
	 * @phpstan-param class-string<T> $className
	 * @phpstan-param mixed  $id
	 * @phpstan-return T|null
	 */
	public function find($className, $id);

	/**
	 * @template T
	 * @phpstan-param T $object
	 * @phpstan-return T
	 */
	public function merge($object);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $className
	 * @phpstan-return ObjectRepository<T>
	 */
	public function getRepository($className);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $entityName
	 * @phpstan-param mixed $id
	 * @phpstan-return T|null
	 */
	public function getReference($entityName, $id);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $entityName
	 * @phpstan-param mixed $identifier
	 *
	 * @phpstan-return T|null
	 */
	public function getPartialReference($entityName, $identifier);

	/**
	 * @template T
	 * @phpstan-param T $entity
	 * @phpstan-param bool $deep
	 * @phpstan-return T
	 */
	public function copy($entity, $deep = false);

}
