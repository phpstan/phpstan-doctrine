<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

class ObjectManagerGetRepositoryDynamicReturnTypeExtension extends AbstractGetRepositoryDynamicReturnTypeExtension
{

	public static function getClass(): string
	{
		return \Doctrine\Common\Persistence\ObjectManager::class;
	}

}
