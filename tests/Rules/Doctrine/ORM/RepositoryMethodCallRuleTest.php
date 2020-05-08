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
		return new RepositoryMethodCallRule(new ObjectMetadataResolver($this->createReflectionProvider(), __DIR__ . '/entity-manager.php', null));
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
				23,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				24,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				25,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				25,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				33,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				34,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				35,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::findOneBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				35,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				43,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				44,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $nonexistent.',
				45,
			],
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntity>::count() - entity PHPStan\Rules\Doctrine\ORM\MyEntity does not have a field named $transient.',
				45,
			],
		]);
	}

}
