<?php declare(strict_types = 1);

namespace PHPStan;

use PHPStan\PhpDoc\StubFilesExtension;
use const PHP_VERSION_ID;

class SortDirectionStubExtension implements StubFilesExtension
{

	public function getFiles(): array
	{
		if (PHP_VERSION_ID >= 80600) {
			return [];
		}

		if (PHP_VERSION_ID < 80100) {
			return [
				__DIR__ . '/../compatibility/SortDirectionInterface.stub',
			];
		}

		return [
			__DIR__ . '/../compatibility/SortDirection.stub',
		];
	}

}
