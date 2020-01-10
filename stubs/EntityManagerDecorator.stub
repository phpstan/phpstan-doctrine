<?php

namespace Doctrine\ORM\Decorator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class EntityManagerDecorator implements EntityManagerInterface
{

	/**
	 * @template T
	 * @phpstan-param class-string<T> $entityName
	 * @phpstan-param mixed  $id
	 * @phpstan-param integer|null $lockMode
	 * @phpstan-param integer|null $lockVersion
	 * @phpstan-return T|null
	 */
	public function find($entityName, $id, $lockMode = null, $lockVersion = null);

	/**
	 * @template T
	 * @phpstan-param T $entity
	 * @phpstan-return T
	 */
	public function merge($entity);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $entityName
	 * @phpstan-return EntityRepository<T>
	 */
	public function getRepository($entityName);

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
