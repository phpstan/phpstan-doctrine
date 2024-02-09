<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\DBAL;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ArrayParameterTypeRule>
 */
final class ArrayParameterTypeRuleTest extends RuleTestCase
{

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/connection.php'], [
			[
				'Parameter at 0 is an array, but is not hinted as such to doctrine.',
				11,
			],
			[
				"Parameter at 'a' is an array, but is not hinted as such to doctrine.",
				20,
			],
			[
				"Parameter at 'a' is an array, but is not hinted as such to doctrine.",
				29,
			],
			[
				"Parameter at 'a' is an array, but is not hinted as such to doctrine.",
				40,
			],
		]);
	}

	protected function getRule(): Rule
	{
		return new ArrayParameterTypeRule();
	}

}
