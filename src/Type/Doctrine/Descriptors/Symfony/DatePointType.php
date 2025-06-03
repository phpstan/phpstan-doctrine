<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Symfony;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Symfony\Component\Clock\DatePoint;

class DatePointType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Symfony\Bridge\Doctrine\Types\DatePointType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectType(DatePoint::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return new ObjectType(DatePoint::class);
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
