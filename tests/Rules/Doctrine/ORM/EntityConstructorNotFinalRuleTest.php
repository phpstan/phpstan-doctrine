<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<EntityConstructorNotFinalRule>
 */
class EntityConstructorNotFinalRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new EntityConstructorNotFinalRule();
	}

	/**
	 * @dataProvider ruleProvider
	 * @param list<array{0: string, 1: int, 2?: string}> $expectedErrors
	 */
	public function testRule(string $file, array $expectedErrors): void
	{
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
