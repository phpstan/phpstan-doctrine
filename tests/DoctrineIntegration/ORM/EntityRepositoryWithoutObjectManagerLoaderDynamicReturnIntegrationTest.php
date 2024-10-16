<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM;

use PHPStan\Testing\LevelsTestCase;

final class EntityRepositoryWithoutObjectManagerLoaderDynamicReturnIntegrationTest extends LevelsTestCase
{

	/**
	 * @return string[][]
	 */
	public static function dataTopics(): array
	{
		return [
			['entityRepositoryDynamicReturn'],
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
		return __DIR__ . '/phpstan-without-object-manager-loader.neon';
	}

}
