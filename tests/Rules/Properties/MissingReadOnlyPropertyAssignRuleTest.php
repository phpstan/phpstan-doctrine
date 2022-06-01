<?php declare(strict_types = 1);

namespace PHPStan\Rules\Properties;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use const PHP_VERSION_ID;

/**
 * @extends RuleTestCase<MissingReadOnlyPropertyAssignRule>
 */
class MissingReadOnlyPropertyAssignRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(MissingReadOnlyPropertyAssignRule::class);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/../../../extension.neon'];
	}

	public function testRule(): void
	{
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Test requires PHP 8.1.');
		}

		$this->analyse([__DIR__ . '/data/missing-readonly-property-assign.php'], [
			[
				'Class MissingReadOnlyPropertyAssign\EntityWithAGeneratedId has an uninitialized readonly property $unassigned. Assign it in the constructor.',
				17,
			],
			[
				'Readonly property MissingReadOnlyPropertyAssign\EntityWithAGeneratedId::$doubleAssigned is already assigned.',
				25,
			],
			[
				'Class MissingReadOnlyPropertyAssign\ReadOnlyEntityWithConstructor has an uninitialized readonly property $id. Assign it in the constructor.',
				46,
			],
		]);
	}

}
