<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Collection;

use PHPStan\Rules\Rule;

/**
 * @extends \PHPStan\Testing\RuleTestCase<VariableTypeReportingRule>
 */
class IsEmptyTypeSpecifyingExtensionTest extends \PHPStan\Testing\RuleTestCase
{

	protected function getRule(): Rule
	{
		return new VariableTypeReportingRule();
	}

	/**
	 * @return \PHPStan\Type\MethodTypeSpecifyingExtension[]
	 */
	protected function getMethodTypeSpecifyingExtensions(): array
	{
		return [
			new IsEmptyTypeSpecifyingExtension(),
		];
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

}
