<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class BigIntType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\BigIntType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		if (!$this->hasDoctrineDbal4()) {
			return TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType());
		}

		if ($this->usePHP64Bit()) { // TODO: Add `|| Bigint is not unsigned`
			return new IntegerType();
		}

		return TypeCombinator::union(
			new IntegerType(),
			TypeCombinator::intersect(new StringType(), new AccessoryNumericStringType()),
		);
	}

	public function getWritableToDatabaseType(): Type
	{
		if (!$this->hasDoctrineDbal4()) {
			return TypeCombinator::union(new StringType(), new IntegerType());
		}

		if ($this->usePHP64Bit()) { // TODO: Add `|| Bigint is not unsigned`
			return new IntegerType();
		}

		return TypeCombinator::union(new StringType(), new IntegerType());
	}

	public function getDatabaseInternalType(): Type
	{
		return new IntegerType();
	}

	public function hasDoctrineDbal4(): bool
	{
		return InstalledVersions::satisfies(new VersionParser(), 'doctrine/dbal', '>= 4');
	}

	public function usePHP64Bit(): bool
	{
		return PHP_INT_SIZE === 8;
	}

}
