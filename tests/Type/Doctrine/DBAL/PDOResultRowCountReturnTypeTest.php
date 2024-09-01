<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\DBAL;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPStan\Testing\TypeInferenceTestCase;

class PDOResultRowCountReturnTypeTest extends TypeInferenceTestCase
{

	/** @return iterable<mixed> */
	public function dataFileAsserts(): iterable
	{
		$versionParser = new VersionParser();
		if (InstalledVersions::satisfies($versionParser, 'doctrine/dbal', '<3')) {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/pdo-result-row-count-dbal-2.php');
		} else {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/pdo-result-row-count.php');
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
		return [__DIR__ . '/pdo.neon'];
	}

}
