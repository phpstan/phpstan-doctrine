<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\GetRepositoryDynamicReturnTypeExtension;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<MagicRepositoryMethodCallRule>
 */
class MagicRepositoryMethodCallRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		$broker = $this->createBroker();

		return new MagicRepositoryMethodCallRule(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null), $broker);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/magic-repository.php'], [
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByTransient() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				24,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByNonexistent() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				25,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findByCustomMethod() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $customMethod.',
				26,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneByTransient() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				35,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneByNonexistent() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				36,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::countByTransient() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				45,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::countByNonexistent() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				46,
			],
			[
				'Call to method PHPStan\Rules\Doctrine\ORM\TestRepository<PHPStan\Rules\Doctrine\ORM\MySecondEntity>::findByTransient() - entity PHPStan\Rules\Doctrine\ORM\MySecondEntity does not have a field named $transient.',
				55,
			],
			[
				'Call to method PHPStan\Rules\Doctrine\ORM\TestRepository<PHPStan\Rules\Doctrine\ORM\MySecondEntity>::findByNonexistent() - entity PHPStan\Rules\Doctrine\ORM\MySecondEntity does not have a field named $nonexistent.',
				56,
			],
		]);
	}

	/**
	 * @return \PHPStan\Type\DynamicMethodReturnTypeExtension[]
	 */
	public function getDynamicMethodReturnTypeExtensions(): array
	{
		return [
			new GetRepositoryDynamicReturnTypeExtension(\Doctrine\ORM\EntityManager::class, new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null)),
		];
	}

}
