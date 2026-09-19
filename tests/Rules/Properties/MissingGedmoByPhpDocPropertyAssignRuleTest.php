<?php declare(strict_types = 1);

namespace PHPStan\Rules\Properties;

use PHPStan\Rules\DeadCode\UnusedPrivatePropertyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use function array_merge;

/**
 * @extends RuleTestCase<UnusedPrivatePropertyRule>
 */
class MissingGedmoByPhpDocPropertyAssignRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(UnusedPrivatePropertyRule::class);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return array_merge(parent::getAdditionalConfigFiles(), [
			__DIR__ . '/../../../extension.neon',
			__DIR__ . '/gedmo-property-assign-rule.neon',
		]);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/gedmo-property-assign-phpdoc.php'], [
			// No errors expected
		]);
	}

}
