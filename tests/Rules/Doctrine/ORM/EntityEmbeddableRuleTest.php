<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<EntityEmbeddableRule>
 */
class EntityEmbeddableRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new EntityEmbeddableRule(
			new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null)
		);
	}

	public function testEmbedded(): void
	{
		$this->analyse([__DIR__ . '/data/EntityWithEmbeddable.php'], []);
	}

	public function testEmbeddedWithWrongTypeHint(): void
	{
		$this->analyse([__DIR__ . '/data/EntityWithBrokenEmbeddable.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenEmbeddable::$embedded type mapping mismatch: mapping specifies PHPStan\Rules\Doctrine\ORM\Embeddable but property expects int.',
				24,
			],
		]);
	}

}
