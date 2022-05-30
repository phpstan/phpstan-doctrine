<?php declare(strict_types = 1);

namespace PHPStan\Rules\Properties;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use const PHP_VERSION_ID;

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
		if (PHP_VERSION_ID < 70400) {
			self::markTestSkipped('Test requires PHP 7.4.');
		}

		$this->analyse([__DIR__ . '/data/missing-readonly-property-assign-phpdoc.php'], []);
	}

}
