<?php declare(strict_types = 1);

namespace PHPStan\Stubs\Doctrine;

use PHPStan\PhpDoc\StubFilesExtension;
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
		$path = dirname(dirname(dirname(__DIR__))) . '/stubs';

		if ($this->bleedingEdge === true) {
			$path .= '/bleedingEdge';
		}

		return [
			$path . '/ORM/QueryBuilder.stub',
		];
	}

}
