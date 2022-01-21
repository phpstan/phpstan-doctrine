<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM;

use Composer\InstalledVersions;
use PHPStan\Testing\LevelsTestCase;

final class EntityManagerIntegrationTest extends LevelsTestCase
{

	/**
	 * @return string[][]
	 */
	public function dataTopics(): array
	{
		$version = InstalledVersions::getVersion('doctrine/dbal');
		$hasDbal3 = $version !== null && strpos($version, '3.') === 0;
		return [
			['entityManagerDynamicReturn'],
			['entityManagerMergeReturn'],
			['customRepositoryUsage'],
			['queryBuilder'],
			[$hasDbal3 ? 'dbalQueryBuilderExecuteDynamicReturnDbal3' : 'dbalQueryBuilderExecuteDynamicReturn'],
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
