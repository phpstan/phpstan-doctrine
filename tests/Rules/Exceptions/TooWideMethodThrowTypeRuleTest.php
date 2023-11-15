<?php declare(strict_types = 1);

namespace PHPStan\Rules\Exceptions;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TooWideMethodThrowTypeRule>
 */
class TooWideMethodThrowTypeRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(TooWideMethodThrowTypeRule::class);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/entity-manager-interface.php'], []);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../../../extension.neon',
		];
	}

}
