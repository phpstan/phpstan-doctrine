<?php

namespace Doctrine\Common\Persistence;

interface ObjectManager
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

}
