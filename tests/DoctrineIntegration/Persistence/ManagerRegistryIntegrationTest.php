<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\Persistence;

use PHPStan\Testing\LevelsTestCase;

final class ManagerRegistryIntegrationTest extends LevelsTestCase
{

	/**
	 * @return string[][]
	 */
	public function dataTopics(): array
	{
		return [
			['managerRegistryRepositoryDynamicReturn'],
		];
	}

	public function getDataPath(): string
	{
		return __DIR__ . '/data';
	}

	public function getPhpStanExecutablePath(): string
	{
		return __DIR__ . '/../../../vendor/phpstan/phpstan/bin/phpstan';
	}

	public function getPhpStanConfigPath(): ?string
	{
		return __DIR__ . '/phpstan.neon';
	}

}
