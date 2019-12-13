<?php

namespace Doctrine\Persistence;

interface ManagerRegistry
{

	/**
	 * @template T
	 * @phpstan-param class-string<T> $persistentObject
	 * @phpstan-param string $persistentManagerName
	 * @phpstan-return ObjectRepository<T>
	 */
	public function getRepository($persistentObject, $persistentManagerName = null);

}
