<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use const E_USER_DEPRECATED;
use function trigger_error;

class EntityManagerGetRepositoryDynamicReturnTypeExtension extends ObjectManagerGetRepositoryDynamicReturnTypeExtension
{

	public function __construct(string $repositoryClass)
	{
		@trigger_error(
			sprintf(
				'Class %s is deprecated and will be removed. Use %s instead',
				self::class,
				ObjectManagerGetRepositoryDynamicReturnTypeExtension::class
			),
			E_USER_DEPRECATED
		);

		parent::__construct('Doctrine\Common\Persistence\ObjectManager', $repositoryClass);
	}

}
