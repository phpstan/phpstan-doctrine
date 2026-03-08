<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<QueryBuilderDqlRule>
 */
class QueryBuilderDqlFirstClassCallableTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new QueryBuilderDqlRule(
			new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', __DIR__ . '/../../../../tmp'),
			true,
		);
	}

	public function testFirstClassCallableInMatchArm(): void
	{
		$this->analyse([__DIR__ . '/data/query-builder-first-class-callable.php'], []);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../../../../extension.neon',
			__DIR__ . '/entity-manager.neon',
		];
	}

}
