<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Configuration;
use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function method_exists;
use const PHP_VERSION_ID;

/**
 * @extends RuleTestCase<EntityNotFinalRule>
 * @runInSeparateProcess
 */
class EntityNotFinalRuleTest extends RuleTestCase
{

	private ?string $objectManagerLoader = null;

	protected function getRule(): Rule
	{
		return new EntityNotFinalRule(
			new ObjectMetadataResolver($this->objectManagerLoader, __DIR__ . '/../../../../tmp'),
		);
	}

	/**
	 * @dataProvider ruleProvider
	 * @param list<array{0: string, 1: int, 2?: string}> $expectedErrors
	 */
	public function testRule(string $file, array $expectedErrors): void
	{
		$this->objectManagerLoader = __DIR__ . '/entity-manager.php';
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @dataProvider ruleWithoutObjectManagerLoaderProvider
	 * @param list<array{0: string, 1: int, 2?: string}> $expectedErrors
	 */
	public function testRuleWithoutObjectManagerLoader(string $file, array $expectedErrors): void
	{
		$this->objectManagerLoader = null;
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @dataProvider ruleWithNativeLazyObjectsProvider
	 * @param list<array{0: string, 1: int, 2?: string}> $expectedErrors
	 */
	public function testRuleWithNativeLazyObjects(string $file, array $expectedErrors): void
	{
		// @phpstan-ignore function.impossibleType, function.alreadyNarrowedType
		if (PHP_VERSION_ID < 80400 || !method_exists(Configuration::class, 'enableNativeLazyObjects')) {
			self::markTestSkipped('Test requires PHP 8.4+ and Doctrine ORM 3.4+.');
		}

		$this->objectManagerLoader = __DIR__ . '/entity-manager-lazy-ghost-objects.php';
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return Iterator<mixed[]>
	 */
	public function ruleProvider(): Iterator
	{
		$finalEntityErrors = self::isNativeLazyObjectsDefault()
			? []
			: [
				[
					'Entity class PHPStan\Rules\Doctrine\ORM\FinalEntity is final which can cause problems with proxies.',
					10,
				],
			];

		yield 'final entity' => [
			__DIR__ . '/data/FinalEntity.php',
			$finalEntityErrors,
		];

		yield 'final annotated entity' => [
			__DIR__ . '/data/FinalAnnotatedEntity.php',
			[],
		];

		yield 'final non-entity' => [
			__DIR__ . '/data/FinalNonEntity.php',
			[],
		];

		yield 'correct entity' => [
			__DIR__ . '/data/MyEntity.php',
			[],
		];

		yield 'final embeddable' => [
			__DIR__ . '/data/FinalEmbeddable.php',
			[],
		];

		yield 'non final embeddable' => [
			__DIR__ . '/data/MyEmbeddable.php',
			[],
		];
	}

	/**
	 * @return Iterator<mixed[]>
	 */
	public function ruleWithoutObjectManagerLoaderProvider(): Iterator
	{
		$nativeLazyObjectsFallback = PHP_VERSION_ID >= 80400
			&& method_exists(Configuration::class, 'enableNativeLazyObjects'); // @phpstan-ignore function.impossibleType, function.alreadyNarrowedType

		$finalEntityErrors = $nativeLazyObjectsFallback
			? []
			: [
				[
					'Entity class PHPStan\Rules\Doctrine\ORM\FinalEntity is final which can cause problems with proxies.',
					10,
				],
			];

		yield 'final entity' => [
			__DIR__ . '/data/FinalEntity.php',
			$finalEntityErrors,
		];

		yield 'final annotated entity' => [
			__DIR__ . '/data/FinalAnnotatedEntity.php',
			[],
		];

		yield 'final non-entity' => [
			__DIR__ . '/data/FinalNonEntity.php',
			[],
		];

		yield 'correct entity' => [
			__DIR__ . '/data/MyEntity.php',
			[],
		];

		yield 'final embeddable' => [
			__DIR__ . '/data/FinalEmbeddable.php',
			[],
		];

		yield 'non final embeddable' => [
			__DIR__ . '/data/MyEmbeddable.php',
			[],
		];
	}

	/**
	 * @return Iterator<mixed[]>
	 */
	public function ruleWithNativeLazyObjectsProvider(): Iterator
	{
		yield 'final entity with native lazy objects' => [
			__DIR__ . '/data/FinalEntity.php',
			[],
		];

		yield 'final annotated entity with native lazy objects' => [
			__DIR__ . '/data/FinalAnnotatedEntity.php',
			[],
		];

		yield 'final non-entity with native lazy objects' => [
			__DIR__ . '/data/FinalNonEntity.php',
			[],
		];

		yield 'correct entity with native lazy objects' => [
			__DIR__ . '/data/MyEntity.php',
			[],
		];

		yield 'final embeddable with native lazy objects' => [
			__DIR__ . '/data/FinalEmbeddable.php',
			[],
		];

		yield 'non final embeddable with native lazy objects' => [
			__DIR__ . '/data/MyEmbeddable.php',
			[],
		];
	}

	private static function isNativeLazyObjectsDefault(): bool
	{
		// @phpstan-ignore function.impossibleType, function.alreadyNarrowedType
		if (!method_exists(Configuration::class, 'isNativeLazyObjectsEnabled')) {
			return false;
		}

		return (new Configuration())->isNativeLazyObjectsEnabled();
	}

}
