<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<EntityConstructorNotFinalRule>
 */
class EntityConstructorNotFinalRuleTest extends RuleTestCase
{

	/** @var string|null */
	private $objectManagerLoader;

	protected function getRule(): Rule
	{
		return new EntityConstructorNotFinalRule(
			new ObjectMetadataResolver($this->objectManagerLoader, __DIR__ . '/../../../../tmp')
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
	 * @dataProvider ruleProvider
	 * @param list<array{0: string, 1: int, 2?: string}> $expectedErrors
	 */
	public function testRuleWithoutObjectManagerLoader(string $file, array $expectedErrors): void
	{
		$this->objectManagerLoader = null;
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return Iterator<mixed[]>
	 */
	public function ruleProvider(): Iterator
	{
		yield 'entity final constructor' => [
			__DIR__ . '/data/EntityFinalConstructor.php',
			[
				[
					'Constructor of class PHPStan\Rules\Doctrine\ORM\EntityFinalConstructor is final which can cause problems with proxies.',
					12,
				],
			],
		];

		yield 'entity non-final constructor' => [
			__DIR__ . '/data/EntityNonFinalConstructor.php',
			[],
		];

		yield 'correct entity' => [
			__DIR__ . '/data/MyEntity.php',
			[],
		];

		yield 'non-entity final constructor' => [
			__DIR__ . '/data/NonEntityFinalConstructor.php',
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

}
