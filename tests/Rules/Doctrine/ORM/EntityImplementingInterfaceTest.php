<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<RepositoryMethodCallRule>
 */
class EntityImplementingInterfaceTest extends RuleTestCase
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
		$this->analyse([__DIR__ . '/data/repository-findBy-interface.php'], [
			[
				'Call to method Doctrine\ORM\EntityRepository<PHPStan\Rules\Doctrine\ORM\MyEntityImplementingInterface>::findBy() - entity PHPStan\Rules\Doctrine\ORM\MyEntityImplementingInterface does not have a field named $nonexistent.',
				22,
			],
		]);
	}

}
