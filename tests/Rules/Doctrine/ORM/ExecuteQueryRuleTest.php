<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ExecuteQueryRule>
 */
class ExecuteQueryRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new ExecuteQueryRule();
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/execute-query.php'], [
			[
				'Only SELECT queries are allowed in the method executeQuery. For statements, you must use executeStatement instead.',
				15,
			],
			[
				'Only SELECT queries are allowed in the method executeQuery. For statements, you must use executeStatement instead.',
				16,
			],
			[
				'Only SELECT queries are allowed in the method executeCacheQuery. For statements, you must use executeStatement instead.',
				20,
			],
			[
				'Only SELECT queries are allowed in the method executeCacheQuery. For statements, you must use executeStatement instead.',
				21,
			],
		]);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../../../../extension.neon',
			__DIR__ . '/entity-manager.neon',
		];
	}

}
