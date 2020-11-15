<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Php\PhpVersion;
use PHPStan\Rules\FunctionCallParametersCheck;
use PHPStan\Rules\Methods\CallMethodsRule;
use PHPStan\Rules\NullsafeCheck;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CallMethodsRule>
 */
class MagicRepositoryMethodCallRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		$broker = $this->createBroker();
		$ruleLevelHelper = new RuleLevelHelper($broker, true, false, true);
		return new CallMethodsRule(
			$broker,
			new FunctionCallParametersCheck($ruleLevelHelper, new NullsafeCheck(), new PhpVersion(PHP_VERSION_ID), true, true, true, true),
			$ruleLevelHelper,
			true,
			true
		);
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
		]);
	}

}
