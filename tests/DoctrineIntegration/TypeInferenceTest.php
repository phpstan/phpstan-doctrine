<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration;

use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Selectable;
use PHPStan\Testing\TypeInferenceTestCase;
use function is_a;

class TypeInferenceTest extends TypeInferenceTestCase
{

	public function dataFileAsserts(): iterable
	{
		yield from $this->gatherAssertTypes(__DIR__ . '/data/getRepository.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/isEmpty.php');
		yield from $this->gatherAssertTypes(__DIR__ . '/data/Collection.php');

		if (is_a(AbstractLazyCollection::class, Selectable::class, true)) { // @phpstan-ignore function.alreadyNarrowedType
			yield from $this->gatherAssertTypes(__DIR__ . '/data/repositoryMatching-collection26.php');
		} else {
			yield from $this->gatherAssertTypes(__DIR__ . '/data/repositoryMatching-collection25.php');
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
		return [__DIR__ . '/../../extension.neon'];
	}

}
