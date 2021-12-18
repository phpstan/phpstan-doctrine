<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM;

use PHPStan\Testing\LevelsTestCase;

final class EntityManagerIntegrationTest extends LevelsTestCase
{

	/**
	 * @return string[][]
	 */
	public function dataTopics(): array
	{
		return [
			['entityManagerDynamicReturn'],
			['entityManagerMergeReturn'],
			['customRepositoryUsage'],
			['queryBuilder'],
			['dbalQueryBuilderExecuteDynamicReturn'],
		];
	}

	public function getDataPath(): string
	{
		return __DIR__ . '/data';
	}

	public function getPhpStanExecutablePath(): string
	{
		return __DIR__ . '/../../../vendor/phpstan/phpstan/phpstan';
	}

	public function getPhpStanConfigPath(): string
	{
		return __DIR__ . '/phpstan.neon';
	}

}
