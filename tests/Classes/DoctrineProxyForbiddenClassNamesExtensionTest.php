<?php declare(strict_types = 1);

namespace PHPStan\Classes;

use PHPStan\Rules\Classes\InstantiationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use function array_merge;

/**
 * @extends RuleTestCase<InstantiationRule>
 */
class DoctrineProxyForbiddenClassNamesExtensionTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(InstantiationRule::class);
	}

	public function testForbiddenClassNameExtension(): void
	{
		$this->analyse(
			[__DIR__ . '/data/forbidden-class-name.php'],
			[
				[
					'Referencing prefixed Doctrine class: App\GeneratedProxy\__CG__\App\TestDoctrineEntity.',
					19,
					'This is most likely unintentional. Did you mean to type \App\TestDoctrineEntity?',
				],
				[
					'Referencing prefixed PHPStan class: _PHPStan_15755dag8c\TestPhpStanEntity.',
					20,
					'This is most likely unintentional. Did you mean to type \TestPhpStanEntity?',
				],
			],
		);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return array_merge(parent::getAdditionalConfigFiles(), [
			__DIR__ . '/phpstan.neon',
		]);
	}

}
