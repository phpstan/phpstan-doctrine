<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use PHPStan\Testing\TypeInferenceTestCase;

class QueryResultDynamicReturnTypeExtensionTest extends TypeInferenceTestCase
{

	/** @return iterable<mixed> */
	public function dataFileAsserts(): iterable
	{
		yield from $this->gatherAssertTypes(__DIR__ . '/../data/QueryResult/queryResult.php');
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
		return [__DIR__ . '/../data/QueryResult/config.neon'];
	}

}
