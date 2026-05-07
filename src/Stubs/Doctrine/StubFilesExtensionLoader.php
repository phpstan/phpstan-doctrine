<?php declare(strict_types = 1);

namespace PHPStan\Stubs\Doctrine;

use Composer\InstalledVersions;
use OutOfBoundsException;
use PHPStan\PhpDoc\StubFilesExtension;
use function class_exists;
use function dirname;
use function strpos;
use function version_compare;

class StubFilesExtensionLoader implements StubFilesExtension
{

	public function getFiles(): array
	{
		$stubsDir = dirname(dirname(dirname(__DIR__))) . '/stubs';
		$files = [];

		if ($this->isInstalledVersion('doctrine/dbal', 4)) {
			$files[] = $stubsDir . '/DBAL/Connection4.stub';
			$files[] = $stubsDir . '/DBAL/ArrayParameterType.stub';
			$files[] = $stubsDir . '/DBAL/ParameterType.stub';
		} else {
			$files[] = $stubsDir . '/DBAL/Connection.stub';
		}

		if ($this->hasLazyServiceEntityRepository()) {
			$files[] = $stubsDir . '/LazyServiceEntityRepository.stub';
		} else {
			$files[] = $stubsDir . '/ServiceEntityRepository.stub';
		}

		try {
			$collectionVersion = class_exists(InstalledVersions::class)
				? InstalledVersions::getVersion('doctrine/collections')
				: null;
		} catch (OutOfBoundsException $e) {
			$collectionVersion = null;
		}
		if ($collectionVersion !== null && strpos($collectionVersion, '1.') === 0) {
			$files[] = $stubsDir . '/Collections/ReadableCollection1.stub';
			$files[] = $stubsDir . '/Collections/Collection1.stub';
		} else {
			$files[] = $stubsDir . '/Collections/ReadableCollection.stub';
			$files[] = $stubsDir . '/Collections/Collection.stub';
		}

		return $files;
	}

	private function hasLazyServiceEntityRepository(): bool
	{
		if (!class_exists(InstalledVersions::class)) {
			return false;
		}

		try {
			$bundleVersion = InstalledVersions::getVersion('doctrine/doctrine-bundle');
		} catch (OutOfBoundsException $e) {
			return false;
		}

		if ($bundleVersion === null) {
			return false;
		}

		return version_compare($bundleVersion, '2.8.1', '>=') && version_compare($bundleVersion, '3.0.0', '<');
	}

	private function isInstalledVersion(string $package, int $majorVersion): bool
	{
		if (!class_exists(InstalledVersions::class)) {
			return false;
		}

		try {
			$installedVersion = InstalledVersions::getVersion($package);
		} catch (OutOfBoundsException $e) {
			return false;
		}

		return $installedVersion !== null && strpos($installedVersion, $majorVersion . '.') === 0;
	}

}
