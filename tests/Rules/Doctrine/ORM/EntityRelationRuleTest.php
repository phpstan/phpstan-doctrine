<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

class EntityRelationRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new EntityRelationRule(
			new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null)
		);
	}

	public function testRule(): void
	{
		$this->analyse([__DIR__ . '/data/EntityWithRelations.php'], []);
	}

}
