<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Symfony;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Component\Clock\DatePoint;

class DatePointTypeDescriptor implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return DatePointType::class;
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
