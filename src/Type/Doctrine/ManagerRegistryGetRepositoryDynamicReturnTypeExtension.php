<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

class ManagerRegistryGetRepositoryDynamicReturnTypeExtension extends AbstractGetRepositoryDynamicReturnTypeExtension
{

	public static function getClass(): string
	{
		return \Doctrine\Common\Persistence\ManagerRegistry::class;
	}

}
