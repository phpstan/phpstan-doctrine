<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Collection;

use PHPStan\Rules\Rule;

/**
 * @extends \PHPStan\Testing\RuleTestCase<VariableTypeReportingRule>
 */
class FirstTypeSpecifyingExtensionTest extends \PHPStan\Testing\RuleTestCase
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
			new FirstTypeSpecifyingExtension(),
		];
	}

	public function testExtension(): void
	{
		$this->analyse([__DIR__ . '/data/collection.php'], [
			[
				'Variable $entityOrFalse is: MyEntity|false',
				18,
			],
			[
				'Variable $false is: false',
				22,
			],
			[
				'Variable $entity is: MyEntity',
				27,
			],
		]);
	}

}
