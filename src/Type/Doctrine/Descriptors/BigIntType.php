<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Composer\InstalledVersions;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function class_exists;
use function strpos;

class BigIntType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\BigIntType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		if ($this->hasDbal4()) {
			return new IntegerType();
		}

		return TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType());
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(new StringType(), new IntegerType());
	}

	public function getDatabaseInternalType(): Type
	{
		return new IntegerType();
	}

	private function hasDbal4(): bool
	{
		if (!class_exists(InstalledVersions::class)) {
			return false;
		}

		$dbalVersion = InstalledVersions::getVersion('doctrine/dbal');
		if ($dbalVersion === null) {
			return false;
		}

		return strpos($dbalVersion, '4.') === 0;
	}

}
