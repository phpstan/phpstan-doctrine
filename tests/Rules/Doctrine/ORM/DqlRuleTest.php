<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

class DqlRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new DqlRule(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null));
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/dql.php'], [
			[
				'DQL: [Syntax Error] line 0, col -1: Error: Expected Doctrine\ORM\Query\Lexer::T_IDENTIFIER, got end of string.',
				30,
			],
			[
				'DQL: [Semantical Error] line 0, col 60 near \'transient = \': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named transient',
				37,
			],
			[
				'DQL: [Semantical Error] line 0, col 14 near \'Foo e\': Error: Class \'Foo\' is not defined.',
				44,
			],
			[
				'DQL: [Semantical Error] line 0, col 17 near \'Foo\': Error: Class \'Foo\' is not defined.',
				51,
			],
			[
				'DQL: [Semantical Error] line 0, col 17 near \'Foo\': Error: Class \'Foo\' is not defined.',
				59,
			],
		]);
	}

}
