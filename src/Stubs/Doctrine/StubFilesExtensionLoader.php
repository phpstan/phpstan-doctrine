<?php declare(strict_types = 1);

namespace PHPStan\Stubs\Doctrine;

use Composer\InstalledVersions;
use OutOfBoundsException;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\PhpDoc\StubFilesExtension;
use function class_exists;
use function dirname;
use function file_exists;
use function strpos;

class StubFilesExtensionLoader implements StubFilesExtension
{

	/** @var Reflector */
	private $reflector;

	/** @var bool */
	private $bleedingEdge;

	public function __construct(
		Reflector $reflector,
		bool $bleedingEdge
	)
	{
		$this->reflector = $reflector;
		$this->bleedingEdge = $bleedingEdge;
	}

	public function getFiles(): array
	{
		$stubsDir = dirname(dirname(dirname(__DIR__))) . '/stubs';
		$path = $stubsDir;

		if ($this->bleedingEdge === true) {
			$path .= '/bleedingEdge';
		}

		$files = [];

		if (file_exists($path . '/DBAL/Connection4.stub') && $this->isInstalledVersion('doctrine/dbal', 4)) {
			$files[] = $path . '/DBAL/Connection4.stub';
			$files[] = $path . '/DBAL/ArrayParameterType.stub';
			$files[] = $path . '/DBAL/ParameterType.stub';
		} else {
			$files[] = $path . '/DBAL/Connection.stub';
		}

		$files[] = $path . '/ORM/QueryBuilder.stub';
		$files[] = $path . '/EntityRepository.stub';

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
