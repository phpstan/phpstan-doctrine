<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Composer\InstalledVersions;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function sprintf;
use function strpos;

/**
 * @extends RuleTestCase<DqlRule>
 */
class DqlRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new DqlRule(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', __DIR__ . '/../../../../tmp'));
	}

	public function testRule(): void
	{
		$ormVersion = InstalledVersions::getVersion('doctrine/orm');
		if (strpos($ormVersion, '3.') === 0) {
			$lexer = 'TokenType';
		} else {
			$lexer = 'Lexer';
		}
		$this->analyse([__DIR__ . '/data/dql.php'], [
			[
				sprintf('DQL: [Syntax Error] line 0, col -1: Error: Expected Doctrine\ORM\Query\%s::T_IDENTIFIER, got end of string.', $lexer),
				35,
			],
			[
				'DQL: [Semantical Error] line 0, col 60 near \'transient = \': Error: Class PHPStan\Rules\Doctrine\ORM\MyEntity has no field or association named transient',
				42,
			],
			[
				'DQL: [Semantical Error] line 0, col 14 near \'Foo e\': Error: Class \'Foo\' is not defined.',
				49,
			],
			[
				'DQL: [Semantical Error] line 0, col 17 near \'Foo\': Error: Class \'Foo\' is not defined.',
				56,
			],
			[
				'DQL: [Semantical Error] line 0, col 17 near \'Foo\': Error: Class \'Foo\' is not defined.',
				64,
			],
		]);
	}

}
