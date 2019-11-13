<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @extends RuleTestCase<EntityRelationRule>
 */
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

		yield 'one to one' => [__DIR__ . '/data/EntityWithBrokenOneToOneRelations.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToOneRelations::$oneToOneNullableProperty type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
					31,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToOneRelations::$oneToOneNullableColumn type mapping mismatch: database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
					37,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToOneRelations::$oneToOneWrongClass type mapping mismatch: database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\MyEntity|null.',
					50,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToOneRelations::$oneToOneWrongClass type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORM\MyEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity|null.',
					50,
				],
			]];

		yield 'many to one' => [__DIR__ . '/data/EntityWithBrokenManyToOneRelations.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToOneRelations::$manyToOneNullableProperty type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
					31,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToOneRelations::$manyToOneNullableColumn type mapping mismatch: database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\AnotherEntity.',
					37,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToOneRelations::$manyToOneWrongClass type mapping mismatch: database can contain PHPStan\Rules\Doctrine\ORM\AnotherEntity|null but property expects PHPStan\Rules\Doctrine\ORM\MyEntity|null.',
					50,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToOneRelations::$manyToOneWrongClass type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORM\MyEntity|null but database expects PHPStan\Rules\Doctrine\ORM\AnotherEntity|null.',
					50,
				],
			]];

		yield 'one to many' => [__DIR__ . '/data/EntityWithBrokenOneToManyRelations.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations::$oneToManyWithIterableAnnotation type mapping mismatch: property can contain iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity> but database expects Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					24,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations::$oneToManyWithCollectionAnnotation type mapping mismatch: property can contain Doctrine\Common\Collections\Collection but database expects Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					30,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations::$oneToManyWithArrayAnnotation type mapping mismatch: database can contain Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity> but property expects array<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					36,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations::$oneToManyWithArrayAnnotation type mapping mismatch: property can contain array<PHPStan\Rules\Doctrine\ORM\AnotherEntity> but database expects Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					36,
				],
			]];

		yield 'many to many' => [__DIR__ . '/data/EntityWithBrokenManyToManyRelations.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToManyRelations::$manyToManyWithIterableAnnotation type mapping mismatch: property can contain iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity> but database expects Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					24,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToManyRelations::$manyToManyWithCollectionAnnotation type mapping mismatch: property can contain Doctrine\Common\Collections\Collection but database expects Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					30,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToManyRelations::$manyToManyWithArrayAnnotation type mapping mismatch: database can contain Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity> but property expects array<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					36,
				],
				[
					'Property PHPStan\Rules\Doctrine\ORM\EntityWithBrokenManyToManyRelations::$manyToManyWithArrayAnnotation type mapping mismatch: property can contain array<PHPStan\Rules\Doctrine\ORM\AnotherEntity> but database expects Doctrine\Common\Collections\Collection&iterable<PHPStan\Rules\Doctrine\ORM\AnotherEntity>.',
					36,
				],
			]];
	}

}
