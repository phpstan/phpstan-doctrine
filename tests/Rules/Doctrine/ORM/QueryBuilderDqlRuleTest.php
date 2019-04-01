<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\CreateQueryBuilderDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\QueryBuilderGetQueryDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilderMethodDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilderTypeSpecifyingExtension;
use PHPStan\Type\Doctrine\QueryGetDqlDynamicReturnTypeExtension;

class QueryBuilderDqlRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new QueryBuilderDqlRule(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null));
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/query-builder-dql.php'], [
			[
				'QueryBuilder: [Syntax Error] line 0, col 66: Error: Expected end of string, got \')\'',
				31,
			],
			[
				'QueryBuilder: [Syntax Error] line 0, col 68: Error: Expected end of string, got \')\'',
				43,
			],
			[
				'QueryBuilder: [Syntax Error] line 0, col 68: Error: Expected end of string, got \')\'',
				55,
			],
			[
				'QueryBuilder: [Semantical Error] line 0, col 60 near \'transient = \': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named transient',
				62,
			],
			[
				'QueryBuilder: [Semantical Error] line 0, col 14 near \'Foo e\': Error: Class \'Foo\' is not defined.',
				71,
			],
		]);
	}

	/**
	 * @return \PHPStan\Type\DynamicMethodReturnTypeExtension[]
	 */
	public function getDynamicMethodReturnTypeExtensions(): array
	{
		return [
			new CreateQueryBuilderDynamicReturnTypeExtension(null),
			new QueryBuilderMethodDynamicReturnTypeExtension(null),
			new QueryBuilderGetQueryDynamicReturnTypeExtension(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null), null),
			new QueryGetDqlDynamicReturnTypeExtension(),
		];
	}

	/**
	 * @return \PHPStan\Type\MethodTypeSpecifyingExtension[]
	 */
	protected function getMethodTypeSpecifyingExtensions(): array
	{
		return [
			new QueryBuilderTypeSpecifyingExtension(null),
		];
	}

}
