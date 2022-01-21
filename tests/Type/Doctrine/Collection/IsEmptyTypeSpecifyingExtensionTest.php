<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Collection;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<VariableTypeReportingRule>
 */
class IsEmptyTypeSpecifyingExtensionTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new VariableTypeReportingRule();
	}

	public function testExtension(): void
	{
		$this->analyse([__DIR__ . '/data/collection.php'], [
			[
				'Variable $entityOrFalse1 is: MyEntity|false',
				18,
			],
			[
				'Variable $entityOrFalse2 is: MyEntity|false',
				21,
			],
			[
				'Variable $false1 is: false',
				25,
			],
			[
				'Variable $false2 is: false',
				28,
			],
			[
				'Variable $entity1 is: MyEntity',
				33,
			],
			[
				'Variable $entity2 is: MyEntity',
				36,
			],
		]);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../../../../extension.neon',
		];
	}

}
