<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PHPStan\Testing\TypeInferenceTestCase;
use const PHP_VERSION_ID;

class DynamicQueryRootsTest extends TypeInferenceTestCase
{

	/** @return iterable<mixed> */
	public function dataFileAsserts(): iterable
	{
		if (PHP_VERSION_ID < 80000) {
			self::markTestSkipped('Test requires PHP 8.0.');
		}

		yield from $this->gatherAssertTypes(__DIR__ . '/../../../Rules/Doctrine/ORM/data-php-8/query-builder-dynamic-roots.php');
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
		return [
			__DIR__ . '/../../../../extension.neon',
			__DIR__ . '/../../../Rules/Doctrine/ORM/entity-manager.neon',
		];
	}

}
