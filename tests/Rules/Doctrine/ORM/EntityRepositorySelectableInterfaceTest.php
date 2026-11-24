<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Generics\ClassAncestorsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use function array_merge;

/**
 * @extends RuleTestCase<ClassAncestorsRule>
 */
class EntityRepositorySelectableInterfaceTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(ClassAncestorsRule::class); // @phpstan-ignore phpstanApi.classConstant
	}

	public function testSelectableInterface(): void
	{
		$this->analyse([__DIR__ . '/data/entityRepositorySelectable.php'], []);
	}

	public static function getAdditionalConfigFiles(): array
	{
		return array_merge(parent::getAdditionalConfigFiles(), [
			__DIR__ . '/../../../../extension.neon',
		]);
	}

}
