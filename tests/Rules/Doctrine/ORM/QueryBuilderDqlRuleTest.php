<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Query\Expr\Base;
use Doctrine\ORM\Query\Expr\OrderBy;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\Query\QueryGetDqlDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilder\CreateQueryBuilderDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilder\Expr\NewExprDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilder\QueryBuilderGetQueryDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilder\QueryBuilderMethodDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\QueryBuilder\QueryBuilderTypeSpecifyingExtension;

class QueryBuilderDqlRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new QueryBuilderDqlRule(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null), true);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/query-builder-dql.php'], [
			[
				"QueryBuilder: [Syntax Error] line 0, col 66: Error: Expected end of string, got ')'\nDQL: SELECT e FROM PHPStan\Rules\Doctrine\ORM\MyEntity e WHERE e.id = 1)",
				31,
			],
			[
				"QueryBuilder: [Syntax Error] line 0, col 68: Error: Expected end of string, got ')'\nDQL: SELECT e FROM PHPStan\Rules\Doctrine\ORM\MyEntity e WHERE e.id = :id)",
				43,
			],
			[
				"QueryBuilder: [Syntax Error] line 0, col 68: Error: Expected end of string, got ')'\nDQL: SELECT e FROM PHPStan\Rules\Doctrine\ORM\MyEntity e WHERE e.id = :id)",
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
			[
				'Could not analyse QueryBuilder with unknown beginning.',
				89,
			],
			[
				'Could not analyse QueryBuilder with dynamic arguments.',
				99,
			],
			[
				'QueryBuilder: [Semantical Error] line 0, col 60 near \'transient = \': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named transient',
				107,
			],
			[
				"QueryBuilder: [Syntax Error] line 0, col 82: Error: Expected end of string, got ')'\nDQL: SELECT e FROM PHPStan\Rules\Doctrine\ORM\MyEntity e WHERE e.id = 1 ORDER BY e.name) ASC",
				129,
			],
			[
				'QueryBuilder: [Semantical Error] line 0, col 78 near \'name ASC\': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named name',
				139,
			],
			[
				'QueryBuilder: [Semantical Error] line 0, col 78 near \'name ASC\': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named name',
				160,
			],
			[
				'QueryBuilder: [Semantical Error] line 0, col 60 near \'transient = 1\': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named transient',
				170,
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
			new QueryBuilderGetQueryDynamicReturnTypeExtension(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null), new ArgumentsProcessor(), null),
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

	/**
	 * @return \PHPStan\Type\DynamicStaticMethodReturnTypeExtension[]
	 */
	public function getDynamicStaticMethodReturnTypeExtensions(): array
	{
		return [
			new NewExprDynamicReturnTypeExtension(OrderBy::class),
			new NewExprDynamicReturnTypeExtension(Base::class),
		];
	}

}
