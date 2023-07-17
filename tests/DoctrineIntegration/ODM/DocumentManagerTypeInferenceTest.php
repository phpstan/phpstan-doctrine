<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ODM;

use PHPStan\Testing\TypeInferenceTestCase;
use const PHP_VERSION_ID;

class DocumentManagerTypeInferenceTest extends TypeInferenceTestCase
{

	/**
	 * @return iterable<mixed>
	 */
	public function dataFileAsserts(): iterable
	{
		if (PHP_VERSION_ID >= 80000) {
			return [];
		}

		yield from $this->gatherAssertTypes(__DIR__ . '/data/documentManagerDynamicReturn.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/documentRepositoryDynamicReturn.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/documentManagerMergeReturn.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/customRepositoryUsage.php');
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
