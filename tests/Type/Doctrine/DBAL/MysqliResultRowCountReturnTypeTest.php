<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\DBAL;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPStan\Testing\TypeInferenceTestCase;

class MysqliResultRowCountReturnTypeTest extends TypeInferenceTestCase
{

	/** @return iterable<mixed> */
	public function dataFileAsserts(): iterable
	{
		if (InstalledVersions::satisfies(new VersionParser(), 'doctrine/dbal', '>=4.0')) {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/mysqli-result-row-count.php');
		} else {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/mysqli-result-row-count-dbal-3.php');
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

	/** @return string[] */
	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/mysqli.neon'];
	}

}
