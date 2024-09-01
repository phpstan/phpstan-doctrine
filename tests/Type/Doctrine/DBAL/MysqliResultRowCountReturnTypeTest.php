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
		$versionParser = new VersionParser();
		if (InstalledVersions::satisfies($versionParser, 'doctrine/dbal', '>=4.0')) {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/mysqli-result-row-count.php');
		} elseif (InstalledVersions::satisfies($versionParser, 'doctrine/dbal', '>=3.0')) {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/mysqli-result-row-count-dbal-3.php');
		} else {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/mysqli-result-row-count-dbal-2.php');
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
