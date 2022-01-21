<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;

class TextType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\TextType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

	public function getDatabaseInternalType(): Type
	{
		return new \PHPStan\Type\StringType();
	}

}
