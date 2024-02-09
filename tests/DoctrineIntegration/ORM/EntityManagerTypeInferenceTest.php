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
		$ormVersion = InstalledVersions::getVersion('doctrine/orm');
		$hasOrm2 = $ormVersion !== null && strpos($ormVersion, '2.') === 0;
		if ($hasOrm2) {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/entityManager-orm2.php');
			yield from $this->gatherAssertTypes(__DIR__ . '/data/entityManagerMergeReturn.php');
		}
		yield from $this->gatherAssertTypes(__DIR__ . '/data/entityManagerDynamicReturn.php');

		yield from $this->gatherAssertTypes(__DIR__ . '/data/customRepositoryUsage.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/queryBuilder.php');

		$dbalVersion = InstalledVersions::getVersion('doctrine/dbal');
		$hasDbal3 = $dbalVersion !== null && strpos($dbalVersion, '3.') === 0;
		$hasDbal4 = $dbalVersion !== null && strpos($dbalVersion, '4.') === 0;

		if ($hasDbal4) {
			// nothing to test
			yield from [];
		} elseif ($hasDbal3) {
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
