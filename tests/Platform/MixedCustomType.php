<?php declare(strict_types = 1);

namespace PHPStan\Platform;

use Doctrine\DBAL\Types\IntegerType;

/**
 * Just a custom type without descriptor registered, so that it results to mixed.
 */
class MixedCustomType extends IntegerType
{

	public const NAME = 'mixed';

	public function getName(): string
	{
		return self::NAME;
	}

}
