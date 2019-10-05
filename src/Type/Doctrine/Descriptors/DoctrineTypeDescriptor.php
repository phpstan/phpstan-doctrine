<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;

interface DoctrineTypeDescriptor
{

	public function getType(): string;

	public function getWritableToPropertyType(): Type;

	public function getWritableToDatabaseType(): Type;

}
