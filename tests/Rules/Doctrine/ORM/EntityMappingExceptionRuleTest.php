<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<EntityMappingExceptionRule>
 */
class EntityMappingExceptionRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new EntityMappingExceptionRule(
			new ObjectMetadataResolver($this->createReflectionProvider(), __DIR__ . '/entity-manager.php', null)
		);
	}

	public function testValidEntity(): void
	{
		$this->analyse([__DIR__ . '/data-attributes/enum-type.php'], []);
	}

	public function testInvalidEntity(): void
	{
		$this->analyse([__DIR__ . '/data-attributes/enum-type-without-pk.php'], [
			[
				'No identifier/primary key specified for Entity "PHPStan\Rules\Doctrine\ORMAttributes\FooWithoutPK". Every Entity must have an identifier/primary key.',
				7,
			],
		]);
	}

}
