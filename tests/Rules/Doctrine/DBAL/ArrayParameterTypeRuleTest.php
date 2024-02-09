<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\DBAL;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ArrayParameterTypeRule>
 */
final class ArrayParameterTypeRuleTest extends RuleTestCase
{

	public function testRuleOlderDbal(): void
	{
		if(InstalledVersions::satisfies(
			new VersionParser(),
			'doctrine/dbal',
			'^3.6 || ^4.0'
		)) {
			self::markTestSkipped('Test requires dbal 2.');
		}
		$this->analyse([__DIR__ . '/data/connection_dbal2.php'], [
			[
				'Parameter at 0 is an array, but is not hinted as such to doctrine.',
				10,
			],
			[
				"Parameter at 'a' is an array, but is not hinted as such to doctrine.",
				19,
			],
			[
				"Parameter at 'a' is an array, but is not hinted as such to doctrine.",
				28,
			],
			[
				"Parameter at 'a' is an array, but is not hinted as such to doctrine.",
				39,
			],
		]);
	}

	public function testRule(): void
	{
		if(InstalledVersions::satisfies(
			new VersionParser(),
			'doctrine/dbal',
			'<3.6'
		)) {
			self::markTestSkipped('Test requires dbal 3 or 4.');
		}
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
