<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\Persistence;

use PHPStan\Testing\TypeInferenceTestCase;

class ManagerRegistryTypeInferenceTest extends TypeInferenceTestCase
{

	/**
	 * @return iterable<mixed>
	 */
	public function dataFileAsserts(): iterable
	{
		yield from $this->gatherAssertTypes(__DIR__ . '/data/managerRegistryRepositoryDynamicReturn.php');
	}

	/**
	 * @dataProvider dataFileAsserts
	 * @param mixed ...$args
	 */
	public function testFileAsserts(
		string $assertType,
		string $file,
		...$args
	): void
	{
		$this->assertFileAsserts($assertType, $file, ...$args);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/phpstan.neon'];
	}

}
