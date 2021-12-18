<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM;

use PHPStan\Testing\LevelsTestCase;

final class EntityRepositoryDynamicReturnIntegrationTest extends LevelsTestCase
{

	/**
	 * @return string[][]
	 */
	public function dataTopics(): array
	{
		return [
			['entityRepositoryDynamicReturn'],
		];
	}

	public function getDataPath(): string
	{
		if (PHP_MAJOR_VERSION === 7 && PHP_MINOR_VERSION === 1) {
			return __DIR__ . '/data-php-7.1';
		}

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
