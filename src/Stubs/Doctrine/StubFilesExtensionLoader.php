<?php declare(strict_types = 1);

namespace PHPStan\Stubs\Doctrine;

use PHPStan\PhpDoc\StubFilesExtension;
use function class_exists;
use function dirname;

class StubFilesExtensionLoader implements StubFilesExtension
{

	/** @var bool */
	private $bleedingEdge;

	public function __construct(
		bool $bleedingEdge
	)
	{
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
			$path . '/ORM/QueryBuilder.stub',
			$path . '/EntityRepository.stub',
		];

		if (class_exists('Doctrine\Bundle\DoctrineBundle\Repository\LazyServiceEntityRepository')) {
			$files[] = $stubsDir . '/LazyServiceEntityRepository.stub';
		} else {
			$files[] = $stubsDir . '/ServiceEntityRepository.stub';
		}

		return $files;
	}

}
