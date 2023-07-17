<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM;

use Composer\InstalledVersions;
use PHPStan\Testing\TypeInferenceTestCase;
use function strpos;

class EntityManagerTypeInferenceTest extends TypeInferenceTestCase
{

	/**
	 * @return iterable<mixed>
	 */
	public function dataFileAsserts(): iterable
	{
		yield from $this->gatherAssertTypes(__DIR__ . '/data/entityManagerDynamicReturn.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/entityManagerMergeReturn.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/customRepositoryUsage.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/queryBuilder.php');

		$version = InstalledVersions::getVersion('doctrine/dbal');
		$hasDbal3 = $version !== null && strpos($version, '3.') === 0;

		if ($hasDbal3) {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/dbalQueryBuilderExecuteDynamicReturnDbal3.php');
		} else {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/dbalQueryBuilderExecuteDynamicReturn.php');
		}
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
