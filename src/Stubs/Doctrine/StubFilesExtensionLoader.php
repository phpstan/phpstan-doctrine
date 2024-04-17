<?php declare(strict_types = 1);

namespace PHPStan\Stubs\Doctrine;

use Composer\InstalledVersions;
use PHPStan\BetterReflection\Reflector\Exception\IdentifierNotFound;
use PHPStan\BetterReflection\Reflector\Reflector;
use PHPStan\PhpDoc\StubFilesExtension;
use function dirname;
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

		$files = [
			$path . '/DBAL/Connection.stub',
			$path . '/ORM/QueryBuilder.stub',
			$path . '/EntityRepository.stub',
		];

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

		$collectionVersion = class_exists(InstalledVersions::class)
			? InstalledVersions::getVersion('doctrine/collections')
			: null;
		if ($collectionVersion !== null && strpos($collectionVersion, '1.') === 0) {
			$files[] = $stubsDir . '/Collections/Collection1.stub';
		} else {
			$files[] = $stubsDir . '/Collections/Collection.stub';
		}

		return $files;
	}

}
