<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Carbon\Doctrine\CarbonImmutableType;
use Carbon\Doctrine\CarbonType;
use Doctrine\DBAL\Types\Type;
use Iterator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\ArrayType;
use PHPStan\Type\Doctrine\Descriptors\BigIntType;
use PHPStan\Type\Doctrine\Descriptors\BinaryType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeImmutableType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeType;
use PHPStan\Type\Doctrine\Descriptors\DateType;
use PHPStan\Type\Doctrine\Descriptors\DecimalType;
use PHPStan\Type\Doctrine\Descriptors\IntegerType;
use PHPStan\Type\Doctrine\Descriptors\JsonType;
use PHPStan\Type\Doctrine\Descriptors\Ramsey\UuidTypeDescriptor;
use PHPStan\Type\Doctrine\Descriptors\ReflectionDescriptor;
use PHPStan\Type\Doctrine\Descriptors\StringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Ramsey\Uuid\Doctrine\UuidType;
use const PHP_VERSION_ID;

/**
 * @extends RuleTestCase<EntityColumnRule>
 */
class EntityColumnRuleTest extends RuleTestCase
{

	/** @var bool */
	private $allowNullablePropertyForRequiredField;

	/** @var string|null */
	private $objectManagerLoader;

	protected function getRule(): Rule
	{
		if (!Type::hasType(CustomType::NAME)) {
			Type::addType(CustomType::NAME, CustomType::class);
		}
		if (!Type::hasType(CustomNumericType::NAME)) {
			Type::addType(CustomNumericType::NAME, CustomNumericType::class);
		}
		if (!Type::hasType(UuidType::NAME)) {
			Type::addType(UuidType::NAME, UuidType::class);
		}
		if (!Type::hasType('carbon')) {
			Type::addType('carbon', CarbonType::class);
		}
		if (!Type::hasType('carbon_immutable')) {
			Type::addType('carbon_immutable', CarbonImmutableType::class);
		}

		return new EntityColumnRule(
			new ObjectMetadataResolver($this->objectManagerLoader),
			new DescriptorRegistry([
				new ArrayType(),
				new BigIntType(),
				new BinaryType(),
				new DateTimeImmutableType(),
				new DateTimeType(),
				new DateType(),
				new DecimalType(),
				new JsonType(),
				new IntegerType(),
				new StringType(),
				new UuidTypeDescriptor(UuidType::class),
				new ReflectionDescriptor(CarbonImmutableType::class, $this->createBroker()),
				new ReflectionDescriptor(CarbonType::class, $this->createBroker()),
				new ReflectionDescriptor(CustomType::class, $this->createBroker()),
				new ReflectionDescriptor(CustomNumericType::class, $this->createBroker()),
			]),
			$this->createReflectionProvider(),
			true,
			$this->allowNullablePropertyForRequiredField,
			true
		);
	}

	/**
	 * @return array<array{string|null}>
	 */
	public function dataObjectManagerLoader(): array
	{
		return [
			[__DIR__ . '/entity-manager.php'],
			[null],
		];
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testRule(?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data/MyBrokenEntity.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$id type mapping mismatch: database can contain string but property expects int|null.',
				19,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$one type mapping mismatch: database can contain string|null but property expects string.',
				25,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$two type mapping mismatch: property can contain string|null but database expects string.',
				31,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$three type mapping mismatch: database can contain DateTime but property expects DateTimeImmutable.',
				37,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$four type mapping mismatch: database can contain DateTimeImmutable but property expects DateTime.',
				43,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$four type mapping mismatch: property can contain DateTime but database expects DateTimeImmutable.',
				43,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$uuidInvalidType type mapping mismatch: database can contain Ramsey\Uuid\UuidInterface but property expects int.',
				72,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$uuidInvalidType type mapping mismatch: property can contain int but database expects Ramsey\Uuid\UuidInterface|string.',
				72,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$arrayOrNull type mapping mismatch: property can contain array|null but database expects array.',
				96,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$arrayOfIntegersOrNull type mapping mismatch: property can contain array|null but database expects array.',
				102,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$numericString type mapping mismatch: database can contain string but property expects numeric-string.',
				126,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$invalidCarbon type mapping mismatch: database can contain Carbon\Carbon but property expects Carbon\CarbonImmutable.',
				132,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$invalidCarbonImmutable type mapping mismatch: database can contain Carbon\CarbonImmutable but property expects Carbon\Carbon.',
				138,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$incompatibleJsonValueObject type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORM\EmptyObject but database expects array|bool|float|int|JsonSerializable|stdClass|string|null.',
				156,
			],
		]);
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testRuleWithAllowedNullableProperty(?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = true;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data/MyBrokenEntity.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$id type mapping mismatch: database can contain string but property expects int|null.',
				19,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$one type mapping mismatch: database can contain string|null but property expects string.',
				25,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$three type mapping mismatch: database can contain DateTime but property expects DateTimeImmutable.',
				37,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$four type mapping mismatch: database can contain DateTimeImmutable but property expects DateTime.',
				43,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$four type mapping mismatch: property can contain DateTime but database expects DateTimeImmutable.',
				43,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$uuidInvalidType type mapping mismatch: database can contain Ramsey\Uuid\UuidInterface but property expects int.',
				72,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$uuidInvalidType type mapping mismatch: property can contain int but database expects Ramsey\Uuid\UuidInterface|string.',
				72,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$numericString type mapping mismatch: database can contain string but property expects numeric-string.',
				126,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$invalidCarbon type mapping mismatch: database can contain Carbon\Carbon but property expects Carbon\CarbonImmutable.',
				132,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$invalidCarbonImmutable type mapping mismatch: database can contain Carbon\CarbonImmutable but property expects Carbon\Carbon.',
				138,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenEntity::$incompatibleJsonValueObject type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORM\EmptyObject but database expects array|bool|float|int|JsonSerializable|stdClass|string|null.',
				156,
			],
		]);
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testRuleOnMyEntity(?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data/MyEntity.php'], []);
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testSuperclass(?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data/MyBrokenSuperclass.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenSuperclass::$five type mapping mismatch: database can contain resource but property expects int.',
				17,
			],
		]);
	}

	/**
	 * @dataProvider generatedIdsProvider
	 * @param mixed[] $expectedErrors
	 */
	public function testGeneratedIds(string $file, array $expectedErrors, ?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return Iterator<string, mixed[]>
	 */
	public function generatedIdsProvider(): Iterator
	{
		yield 'not nullable' => [__DIR__ . '/data/GeneratedIdEntity1.php', [], __DIR__ . '/entity-manager.php'];
		yield 'not nullable 2' => [__DIR__ . '/data/GeneratedIdEntity1.php', [], null];
		yield 'nullable column' => [
			__DIR__ . '/data/GeneratedIdEntity2.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity2::$id type mapping mismatch: database can contain string|null but property expects string.',
					19,
				],
			],
			__DIR__ . '/entity-manager.php',
		];
		yield 'nullable column 2' => [
			__DIR__ . '/data/GeneratedIdEntity2.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity2::$id type mapping mismatch: database can contain string|null but property expects string.',
					19,
				],
			],
			null,
		];
		yield 'nullable property' => [__DIR__ . '/data/GeneratedIdEntity3.php', [], __DIR__ . '/entity-manager.php'];
		yield 'nullable property 2' => [__DIR__ . '/data/GeneratedIdEntity3.php', [], null];
		yield 'nullable both' => [__DIR__ . '/data/GeneratedIdEntity4.php', [], __DIR__ . '/entity-manager.php'];
		yield 'nullable both 2' => [__DIR__ . '/data/GeneratedIdEntity4.php', [], null];
		yield 'composite' => [__DIR__ . '/data/CompositePrimaryKeyEntity1.php', [], __DIR__ . '/entity-manager.php'];
		yield 'composite 2' => [__DIR__ . '/data/CompositePrimaryKeyEntity1.php', [], null];
		yield 'no generated value 1' => [__DIR__ . '/data/GeneratedIdEntity5.php', [], __DIR__ . '/entity-manager.php'];
		yield 'no generated value 1 2' => [__DIR__ . '/data/GeneratedIdEntity5.php', [], null];
		yield 'no generated value 2' => [__DIR__ . '/data/GeneratedIdEntity6.php', [
			[
				'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity6::$id type mapping mismatch: property can contain int|null but database expects int.',
				18,
			],
		], __DIR__ . '/entity-manager.php'];
		yield 'no generated value 2 2' => [__DIR__ . '/data/GeneratedIdEntity6.php', [
			[
				'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity6::$id type mapping mismatch: property can contain int|null but database expects int.',
				18,
			],
		], null];
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testCustomType(?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data/EntityWithCustomType.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithCustomType::$foo type mapping mismatch: database can contain DateTimeInterface but property expects int.',
				24,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithCustomType::$foo type mapping mismatch: property can contain int but database expects array.',
				24,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithCustomType::$numeric type mapping mismatch: property can contain string but database expects numeric-string.',
				30,
			],
		]);
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testUnknownType(?string $objectManagerLoader): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data/EntityWithUnknownType.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithUnknownType::$foo: Doctrine type "unknown" does not have any registered descriptor.',
				24,
			],
		]);
	}

	/**
	 * @dataProvider dataObjectManagerLoader
	 */
	public function testEnumType(?string $objectManagerLoader): void
	{
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Test requires PHP 8.1.');
		}

		$this->allowNullablePropertyForRequiredField = false;
		$this->objectManagerLoader = $objectManagerLoader;
		$this->analyse([__DIR__ . '/data-attributes/enum-type.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORMAttributes\Foo::$type2 type mapping mismatch: database can contain PHPStan\Rules\Doctrine\ORMAttributes\FooEnum but property expects PHPStan\Rules\Doctrine\ORMAttributes\BarEnum.',
				35,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORMAttributes\Foo::$type2 type mapping mismatch: property can contain PHPStan\Rules\Doctrine\ORMAttributes\BarEnum but database expects PHPStan\Rules\Doctrine\ORMAttributes\FooEnum.',
				35,
			],
			[
				'Property PHPStan\Rules\Doctrine\ORMAttributes\Foo::$type3 type mapping mismatch: backing type string of enum PHPStan\Rules\Doctrine\ORMAttributes\FooEnum does not match database type int.',
				38,
			],
		]);
	}

}
