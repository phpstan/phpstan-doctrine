<?php declare(strict_types = 1);

namespace PHPStan\Rules\Properties;

use Iterator;
use PHPStan\Rules\DeadCode\UnusedPrivatePropertyRule;
use PHPStan\Rules\Gedmo\PropertiesExtension;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use const PHP_VERSION_ID;

/**
 * @extends RuleTestCase<UnusedPrivatePropertyRule>
 */
class MissingGedmoPropertyAssignRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(UnusedPrivatePropertyRule::class);
	}

	protected function getReadWritePropertiesExtensions(): array
	{
		return [
			new PropertiesExtension(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', __DIR__ . '/../../../../tmp')),
		];
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/../../../extension.neon'];
	}

	/**
	 * @dataProvider ruleProvider
	 * @param list<array{0: string, 1: int, 2?: string}> $expectedErrors
	 */
	public function testRule(string $file, array $expectedErrors): void
	{
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Test requires PHP 8.1.');
		}

		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return Iterator<mixed[]>
	 */
	public function ruleProvider(): Iterator
	{
		yield 'entity with gedmo attributes' => [
			__DIR__ . '/data/gedmo-property-assign.php',
			[
				// No errors expected
			],
		];

		yield 'non-entity with gedmo attributes' => [
			__DIR__ . '/data/gedmo-property-assign-non-entity.php',
			[
				[
					'Property MissingGedmoWrittenPropertyAssign\NonEntityWithAGemdoLocaleField::$locale is unused.',
					10,
					'See: https://phpstan.org/developing-extensions/always-read-written-properties',
				],
			],
		];
	}

}
