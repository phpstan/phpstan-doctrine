<?php

namespace Doctrine\Common\Persistence;

class ObjectManagerDecorator
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

}
