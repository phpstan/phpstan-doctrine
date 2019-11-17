<?php

namespace Doctrine\Common\Persistence;

interface ManagerRegistry
{

	/**
	 * @template T
	 * @param class-string<T> $persistentObject
	 * @param string $persistentManagerName
	 * @return ObjectRepository<T>
	 */
	public function getRepository($persistentObject, $persistentManagerName = null);

}
