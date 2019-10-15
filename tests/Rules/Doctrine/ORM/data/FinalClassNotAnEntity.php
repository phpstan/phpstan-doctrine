<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

final class FinalClassNotAnEntity
{

	/**
	 * @return mixed[]
	 */
	final public function getData(): array
	{
		return [];
	}

}
