<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use const E_USER_DEPRECATED;
use function trigger_error;

class EntityRepositoryDynamicReturnTypeExtension extends ObjectRepositoryDynamicReturnTypeExtension
{

	public function __construct()
	{
		@trigger_error(
			sprintf(
				'Class %s is deprecated and will be removed. Use %s instead',
				self::class,
				ObjectRepositoryDynamicReturnTypeExtension::class
			),
			E_USER_DEPRECATED
		);
	}

}
