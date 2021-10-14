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

/**
 * @extends RuleTestCase<EntityColumnRule>
 */
class EntityColumnRuleTest extends RuleTestCase
{

	/** @var bool */
	private $allowNullablePropertyForRequiredField;

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
			Type::addType('carbon', \Carbon\Doctrine\CarbonType::class);
		}
		if (!Type::hasType('carbon_immutable')) {
			Type::addType('carbon_immutable', \Carbon\Doctrine\CarbonImmutableType::class);
		}

		return new EntityColumnRule(
			new ObjectMetadataResolver($this->createReflectionProvider(), __DIR__ . '/entity-manager.php', null),
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
			true,
			$this->allowNullablePropertyForRequiredField
		);
	}

	public function testRule(): void
	{
		$this->allowNullablePropertyForRequiredField = false;
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

	public function testRuleWithAllowedNullableProperty(): void
	{
		$this->allowNullablePropertyForRequiredField = true;
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

	public function testRuleOnMyEntity(): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->analyse([__DIR__ . '/data/MyEntity.php'], []);
	}

	public function testSuperclass(): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->analyse([__DIR__ . '/data/MyBrokenSuperclass.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\MyBrokenSuperclass::$five type mapping mismatch: database can contain resource but property expects int.',
				17,
			],
		]);
	}

	/**
	 * @dataProvider generatedIdsProvider
	 * @param string $file
	 * @param mixed[] $expectedErrors
	 */
	public function testGeneratedIds(string $file, array $expectedErrors): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->analyse([$file], $expectedErrors);
	}

	/**
	 * @return \Iterator<string, mixed[]>
	 */
	public function generatedIdsProvider(): Iterator
	{
		yield 'not nullable' => [__DIR__ . '/data/GeneratedIdEntity1.php', []];
		yield 'nullable column' => [
			__DIR__ . '/data/GeneratedIdEntity2.php',
			[
				[
					'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity2::$id type mapping mismatch: database can contain string|null but property expects string.',
					19,
				],
			],
		];
		yield 'nullable property' => [__DIR__ . '/data/GeneratedIdEntity3.php', []];
		yield 'nullable both' => [__DIR__ . '/data/GeneratedIdEntity4.php', []];
		yield 'composite' => [__DIR__ . '/data/CompositePrimaryKeyEntity1.php', []];
		yield 'no generated value 1' => [__DIR__ . '/data/GeneratedIdEntity5.php', []];
		yield 'no generated value 2' => [__DIR__ . '/data/GeneratedIdEntity6.php', [
			[
				'Property PHPStan\Rules\Doctrine\ORM\GeneratedIdEntity6::$id type mapping mismatch: property can contain int|null but database expects int.',
				18,
			],
		]];
	}

	public function testCustomType(): void
	{
		$this->allowNullablePropertyForRequiredField = false;
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

	public function testUnknownType(): void
	{
		$this->allowNullablePropertyForRequiredField = false;
		$this->analyse([__DIR__ . '/data/EntityWithUnknownType.php'], [
			[
				'Property PHPStan\Rules\Doctrine\ORM\EntityWithUnknownType::$foo: Doctrine type "unknown" does not have any registered descriptor.',
				24,
			],
		]);
	}

}
