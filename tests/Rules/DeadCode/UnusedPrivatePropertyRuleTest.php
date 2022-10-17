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
		return [
			__DIR__ . '/../../../extension.neon',
			__DIR__ . '/entity-manager.neon',
		];
	}

	public function testRule(): void
	{
		if (PHP_VERSION_ID < 70400) {
			self::markTestSkipped('Test requires PHP 7.4.');
		}

		$this->analyse([__DIR__ . '/data/unused-private-property.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\UnusedPrivateProperty\EntityWithAGeneratedId::$unused is never written, only read.',
				23,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\UnusedPrivateProperty\EntityWithAGeneratedId::$unused2 is unused.',
				25,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\UnusedPrivateProperty\ReadOnlyEntityWithConstructor::$id is never written, only read.',
				53,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
		]);
	}

	public function testBug383(): void
	{
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Test requires PHP 8.1.');
		}

		$this->analyse([__DIR__ . '/data/bug-383.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORMAttributes\Bug383\Campus::$id is never written, only read.',
				13,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
			[
				'Property PHPStan\Rules\Doctrine\ORMAttributes\Bug383\Campus::$students is never written, only read.',
				19,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
			[
				'Property PHPStan\Rules\Doctrine\ORMAttributes\Bug383\Student::$campus is unused.',
				29,
				'See: https://phpstan.org/developing-extensions/always-read-written-properties',
			],
		]);
	}

}
