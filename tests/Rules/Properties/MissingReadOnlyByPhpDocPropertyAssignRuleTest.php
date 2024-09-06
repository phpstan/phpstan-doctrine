<?php declare(strict_types = 1);

namespace PHPStan\Rules\Properties;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MissingReadOnlyByPhpDocPropertyAssignRule>
 */
class MissingReadOnlyByPhpDocPropertyAssignRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(MissingReadOnlyByPhpDocPropertyAssignRule::class);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/../../../extension.neon'];
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/missing-readonly-property-assign-phpdoc.php'], [
			[
				'Class MissingReadOnlyPropertyAssignPhpDoc\EntityWithAGeneratedId has an uninitialized @readonly property $unassigned. Assign it in the constructor.',
				25,
			],
			[
				'@readonly property MissingReadOnlyPropertyAssignPhpDoc\EntityWithAGeneratedId::$doubleAssigned is already assigned.',
				36,
			],
			[
				'Class MissingReadOnlyPropertyAssignPhpDoc\ReadOnlyEntityWithConstructor has an uninitialized @readonly property $id. Assign it in the constructor.',
				67,
			],
		]);
	}

}
