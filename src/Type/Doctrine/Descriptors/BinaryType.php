<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Composer\InstalledVersions;
use PHPStan\Type\MixedType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use function class_exists;
use function strpos;

class BinaryType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\BinaryType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		if ($this->hasDbal4()) {
			return new StringType();
		}

		return new ResourceType();
	}

	public function getWritableToDatabaseType(): Type
	{
		return new MixedType();
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
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
