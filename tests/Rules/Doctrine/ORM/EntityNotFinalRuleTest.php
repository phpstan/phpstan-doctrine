<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<EntityNotFinalRule>
 */
class EntityNotFinalRuleTest extends RuleTestCase
{

	/** @var string|null */
	private $objectManagerLoader;

	protected function getRule(): Rule
	{
		return new EntityNotFinalRule(
			new ObjectMetadataResolver($this->objectManagerLoader)
		);
	}

	/**
	 * @dataProvider ruleProvider
	 * @param string $file
	 * @param mixed[] $expectedErrors
	 */
	public function testRule(string $file, array $expectedErrors): void
	{
		$this->objectManagerLoader = __DIR__ . '/entity-manager.php';
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @dataProvider ruleProvider
	 * @param string $file
	 * @param mixed[] $expectedErrors
	 */
	public function testRuleWithoutObjectManagerLoader(string $file, array $expectedErrors): void
	{
		$this->objectManagerLoader = null;
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return \Iterator<mixed[]>
	 */
	public function ruleProvider(): Iterator
	{
		yield 'final entity' => [
			__DIR__ . '/data/FinalEntity.php',
			[
				[
					'Entity class PHPStan\Rules\Doctrine\ORM\FinalEntity is final which can cause problems with proxies.',
					10,
				],
			],
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
	}

}
