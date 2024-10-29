<?php declare(strict_types = 1);

namespace PHPStan\Stubs\Doctrine;

use Composer\InstalledVersions;
use OutOfBoundsException;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\PhpDoc\StubFilesExtension;
use function class_exists;
use function dirname;
use function strpos;

class StubFilesExtensionLoader implements StubFilesExtension
{

	/** @var Reflector */
	private $reflector;

	public function __construct(
		Reflector $reflector
	)
	{
		$this->reflector = $reflector;
	}

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

		$hasLazyServiceEntityRepositoryAsParent = false;

		try {
			$serviceEntityRepository = $this->reflector->reflectClass('Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository');
			if ($serviceEntityRepository->getParentClass() !== null) {
				/** @var class-string $lazyServiceEntityRepositoryName */
				$lazyServiceEntityRepositoryName = 'Doctrine\Bundle\DoctrineBundle\Repository\LazyServiceEntityRepository';
				$hasLazyServiceEntityRepositoryAsParent = $serviceEntityRepository->getParentClass()->getName() === $lazyServiceEntityRepositoryName;
			}
		} catch (IdentifierNotFound $e) {
			// pass
		}

		if ($hasLazyServiceEntityRepositoryAsParent) {
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
