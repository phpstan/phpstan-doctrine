<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

class ManagerRegistryGetRepositoryDynamicReturnTypeExtension extends GetRepositoryDynamicReturnTypeExtension
{

	public function getClass(): string
	{
		return 'Doctrine\Common\Persistence\ManagerRegistry';
	}

}
