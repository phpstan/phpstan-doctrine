<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Iterator;
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

	/**
	 * @dataProvider ruleProvider
	 */
	public function testRule(string $file, array $expectedErrors): void
	{
		$this->analyse([$file], $expectedErrors);
	}

	public function ruleProvider(): Iterator
	{
		yield 'nice entity' => [__DIR__ . '/data/EntityWithRelations.php', []];

		yield 'one to one' => [__DIR__ . '/data/EntityWithBrokenOneToOneRelations.php', [
			[
				'Property can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
				31,
			],
			[
				'Database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
				37,
			],
			[
				'Database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\MyEntity|null.',
				50,
			],
			[
				'Property can contain PHPStan\Rules\Doctrine\ORM\MyEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity|null.',
				50,
			]
		]];

		yield 'many to one' => [__DIR__ . '/data/EntityWithBrokenManyToOneRelations.php', [
			[
				'Property can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
				31,
			],
			[
				'Database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
				37,
			],
			[
				'Database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\MyEntity|null.',
				50,
			],
			[
				'Property can contain PHPStan\Rules\Doctrine\ORM\MyEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity|null.',
				50,
			]
		]];
	}

}
