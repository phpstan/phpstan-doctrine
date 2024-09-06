<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\DBAL;

use PHPStan\Testing\TypeInferenceTestCase;

class PDOResultRowCountReturnTypeTest extends TypeInferenceTestCase
{

	/** @return iterable<mixed> */
	public function dataFileAsserts(): iterable
	{
		yield from $this->gatherAssertTypes(__DIR__ . '/data/pdo-result-row-count.php');
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
