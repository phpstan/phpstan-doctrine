<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use const E_USER_DEPRECATED;
use function trigger_error;

class EntityRepositoryType extends ObjectRepositoryType
{

	public function __construct(string $entityClass, string $repositoryClass)
	{
		@trigger_error(
			sprintf(
				'Class %s is deprecated and will be removed. Use %s instead',
				self::class,
				ObjectRepositoryType::class
			),
			E_USER_DEPRECATED
		);

		parent::__construct($entityClass, $repositoryClass);
	}

}
