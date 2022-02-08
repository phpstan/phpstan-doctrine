<?php declare(strict_types = 1);

namespace PHPStan\LiteralString\Doctrine;

use PHPStan\PhpDoc\StubFilesExtension;

class LiteralStringStubFilesExtension implements StubFilesExtension
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
		$path = __DIR__;

		if ($this->bleedingEdge !== true) {
			$path .= '/../../..';
		}

		return [
			$path . '/stubs/ORM/QueryBuilder.stub',
		];
	}

}
