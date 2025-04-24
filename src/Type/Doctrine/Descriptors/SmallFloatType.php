<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

class SmallFloatType extends FloatType
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\SmallFloatType::class;
	}

}
