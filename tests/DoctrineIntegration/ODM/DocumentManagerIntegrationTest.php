<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ODM;

use PHPStan\Testing\LevelsTestCase;

final class DocumentManagerIntegrationTest extends LevelsTestCase
{

	/**
	 * @return string[][]
	 */
	public function dataTopics(): array
	{
		return [
			['documentManagerDynamicReturn'],
			['documentRepositoryDynamicReturn'],
			['documentManagerMergeReturn'],
		];
	}

	public function getDataPath(): string
	{
		return __DIR__ . '/data';
	}

	public function getPhpStanExecutablePath(): string
	{
		return __DIR__ . '/../../../vendor/bin/phpstan';
	}

	public function getPhpStanConfigPath(): ?string
	{
		return __DIR__ . '/phpstan.neon';
	}

}
