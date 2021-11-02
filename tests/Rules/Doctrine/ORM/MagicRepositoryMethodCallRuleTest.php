<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Methods\CallMethodsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CallMethodsRule>
 */
class MagicRepositoryMethodCallRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return self::getContainer()->getByType(CallMethodsRule::class);
	}

	/**
	 * @return string[]
	 */
	public static function getAdditionalConfigFiles(): array
	{
		return [__DIR__ . '/magic-repository.neon'];
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/magic-repository.php'], [
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByTransient().',
				24,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByNonexistent().',
				25,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByCustomMethod().',
				26,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneByTransient().',
				35,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneByNonexistent().',
				36,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::countBy().',
				42,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::countByTransient().',
				45,
			],
			[
				'Call to an undefined method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::countByNonexistent().',
				46,
			],
			[
				'Call to an undefined method PHPStan\Rules\Doctrine\ORM\TestRepository<PHPStan\Rules\Doctrine\ORM\MySecondEntity>::findByTransient().',
				55,
			],
			[
				'Call to an undefined method PHPStan\Rules\Doctrine\ORM\TestRepository<PHPStan\Rules\Doctrine\ORM\MySecondEntity>::findByNonexistent().',
				56,
			],
			[
				'Parameter #2 $orderBy of method Doctrine\Persistence\ObjectRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByTitle() expects array<string, string>|null, array<int, string> given.',
				65,
			],
			[
				'Parameter #3 $limit of method Doctrine\Persistence\ObjectRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByTitle() expects int|null, string given.',
				68,
			],
			[
				'Parameter #4 $offset of method Doctrine\Persistence\ObjectRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByTitle() expects int|null, string given.',
				71,
			],
			[
				'Parameter #2 $orderBy of method Doctrine\Persistence\ObjectRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneByTitle() expects array<string, string>|null, array<int, string> given.',
				79,
			],
			[
				'Method Doctrine\Persistence\ObjectRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::countByTitle() invoked with 2 parameters, 1 required.',
				85,
			],
		]);
	}

}
