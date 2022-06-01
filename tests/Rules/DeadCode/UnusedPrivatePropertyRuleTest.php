<?php declare(strict_types = 1);

namespace PHPStan\Rules\DeadCode;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use const PHP_VERSION_ID;

/**
 * @extends RuleTestCase<UnusedPrivatePropertyRule>
 */
class UnusedPrivatePropertyRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(UnusedPrivatePropertyRule::class);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/../../../extension.neon'];
	}

	public function testRule(): void
	{
		if (PHP_VERSION_ID < 70400) {
			self::markTestSkipped('Test requires PHP 7.4.');
		}

		$this->analyse([__DIR__ . '/data/unused-private-property.php'], [
			[
				'Property UnusedPrivateProperty\EntityWithAGeneratedId::$unused is never written, only read.',
				23,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
			[
				'Property UnusedPrivateProperty\EntityWithAGeneratedId::$unused2 is unused.',
				25,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
			[
				'Property UnusedPrivateProperty\ReadOnlyEntityWithConstructor::$id is never written, only read.',
				53,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
		]);
	}

}
