<?php

namespace Doctrine\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;

interface EntityManagerInterface extends ObjectManager
{

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
