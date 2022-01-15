<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use const PHP_VERSION_ID;

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
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Test requires PHP 8.1.');
		}
		$this->analyse([__DIR__ . '/data-attributes/enum-type.php'], []);
	}

	public function testInvalidEntity(): void
	{
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Test requires PHP 8.1.');
		}
		$this->analyse([__DIR__ . '/data-attributes/enum-type-without-pk.php'], [
			[
				'No identifier/primary key specified for Entity "PHPStan\Rules\Doctrine\ORMAttributes\FooWithoutPK". Every Entity must have an identifier/primary key.',
				7,
			],
		]);
	}

}
