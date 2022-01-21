<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration;

use PHPStan\Testing\TypeInferenceTestCase;

class TypeInferenceTest extends TypeInferenceTestCase
{

	/**
	 * @return iterable<mixed>
	 */
	public function dataFileAsserts(): iterable
	{
		yield from $this->gatherAssertTypes(__DIR__ . '/data/getRepository.php');
	}

	/**
	 * @dataProvider dataFileAsserts
	 */
	public function testFileAsserts(
		string $assertType,
		string $file,
		mixed ...$args
	): void
	{
		$this->assertFileAsserts($assertType, $file, ...$args);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/../../extension.neon'];
	}

}
