<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<RepositoryMethodCallRule>
 */
class RepositoryMethodCallRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new RepositoryMethodCallRule(new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', __DIR__ . '/../../../../tmp'), true);
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
		$this->analyse([__DIR__ . '/data/repository-findBy-etc.php'], [
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				24,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				25,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				26,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				26,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				34,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				35,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				36,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				36,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				44,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				45,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				46,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				46,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				57,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				58,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				59,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				59,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				69,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				70,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				71,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				71,
			],
		]);
	}

}
