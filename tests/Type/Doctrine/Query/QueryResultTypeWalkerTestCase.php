<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Composer\InstalledVersions;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Query\AST\TypedExpression;
use Doctrine\ORM\Tools\SchemaTool;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use QueryResult\Entities\Embedded;
use QueryResult\Entities\JoinedChild;
use QueryResult\Entities\Many;
use QueryResult\Entities\NestedEmbedded;
use QueryResult\Entities\One;
use QueryResult\Entities\SingleTableChild;
use QueryResult\EntitiesEnum\EntityWithEnum;
use QueryResult\EntitiesEnum\IntEnum;
use QueryResult\EntitiesEnum\StringEnum;
use Throwable;
use function array_merge;
use function array_shift;
use function assert;
use function class_exists;
use function count;
use function property_exists;
use function sprintf;
use function version_compare;
use const PHP_VERSION_ID;

abstract class QueryResultTypeWalkerTestCase extends PHPStanTestCase
{

	/** @var EntityManagerInterface */
	protected static $em;

	/** @var DescriptorRegistry */
	private $descriptorRegistry;

	abstract protected static function getEntityManagerPath(): string;

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../data/QueryResult/config.neon',
		];
	}

	public static function setUpBeforeClass(): void
	{
		$em = require static::getEntityManagerPath();
		self::$em = $em;

		$schemaTool = new SchemaTool($em);
		$classes = $em->getMetadataFactory()->getAllMetadata();
		$schemaTool->createSchema($classes);

		$dataOne = [
			'intColumn' => [1, 2],
			'stringColumn' => ['A', 'B'],
			'stringNullColumn' => ['A', null],
		];

		$dataMany = [
			'intColumn' => [1, 2],
			'stringColumn' => ['A', 'B'],
			'stringNullColumn' => ['A', null],
		];

		$dataJoinedInheritance = [
			'parentColumn' => [1, 2],
			'parentNullColumn' => [1, null],
			'childColumn' => [1, 2],
			'childNullColumn' => [1, null],
		];

		$dataSingleTableInheritance = [
			'parentColumn' => [1, 2],
			'parentNullColumn' => [1, null],
			'childNullColumn' => [1, null],
		];

		$id = 1;

		foreach (self::combinations($dataOne) as $combination) {
			[$intColumn, $stringColumn, $stringNullColumn] = $combination;
			$one = new One();
			$one->id = (string) $id++;
			$one->intColumn = $intColumn;
			$one->stringColumn = $stringColumn;
			$one->stringNullColumn = $stringNullColumn;
			$embedded = new Embedded();
			$embedded->intColumn = $intColumn;
			$embedded->stringColumn = $stringColumn;
			$embedded->stringNullColumn = $stringNullColumn;
			$nestedEmbedded = new NestedEmbedded();
			$nestedEmbedded->intColumn = $intColumn;
			$nestedEmbedded->stringColumn = $stringColumn;
			$nestedEmbedded->stringNullColumn = $stringNullColumn;
			$embedded->nestedEmbedded = $nestedEmbedded;
			$one->embedded = $embedded;
			$one->manies = new ArrayCollection();

			foreach (self::combinations($dataMany) as $combinationMany) {
				[$intColumnMany, $stringColumnMany, $stringNullColumnMany] = $combinationMany;
				$many = new Many();
				$many->id = (string) $id++;
				$many->intColumn = $intColumnMany;
				$many->stringColumn = $stringColumnMany;
				$many->stringNullColumn = $stringNullColumnMany;
				$many->datetimeColumn = new DateTime('2001-01-01 00:00:00');
				$many->datetimeImmutableColumn = new DateTimeImmutable('2001-01-01 00:00:00');
				$many->simpleArrayColumn = ['foo'];
				$many->one = $one;
				$one->manies->add($many);
				$em->persist($many);
			}

			$em->persist($one);
		}

		foreach (self::combinations($dataJoinedInheritance) as $combination) {
			[$parentColumn, $parentNullColumn, $childColumn, $childNullColumn] = $combination;
			$child = new JoinedChild();
			$child->id = (string) $id++;
			$child->parentColumn = $parentColumn;
			$child->parentNullColumn = $parentNullColumn;
			$child->childColumn = $childColumn;
			$child->childNullColumn = $childNullColumn;
			$em->persist($child);
		}

		foreach (self::combinations($dataSingleTableInheritance) as $combination) {
			[$parentColumn, $parentNullColumn, $childNullColumn] = $combination;
			$child = new SingleTableChild();
			$child->id = (string) $id++;
			$child->parentColumn = $parentColumn;
			$child->parentNullColumn = $parentNullColumn;
			$child->childNullColumn = $childNullColumn;
			$em->persist($child);
		}

		if (property_exists(Column::class, 'enumType') && PHP_VERSION_ID >= 80100) {
			assert(class_exists(StringEnum::class));
			assert(class_exists(IntEnum::class));

			$entityWithEnum = new EntityWithEnum();
			$entityWithEnum->id = '1';
			$entityWithEnum->stringEnumColumn = StringEnum::A;
			$entityWithEnum->intEnumColumn = IntEnum::A;
			$entityWithEnum->intEnumOnStringColumn = IntEnum::A;
			$em->persist($entityWithEnum);
		}

		$em->flush();
	}

	public static function tearDownAfterClass(): void
	{
		self::$em->clear();
	}

	public function setUp(): void
	{
		$this->descriptorRegistry = self::getContainer()->getByType(DescriptorRegistry::class);
	}

	/**
	 * @dataProvider getTestData
	 */
	public function test(Type $expectedType, string $dql, ?string $expectedExceptionMessage = null, ?string $expectedDeprecationMessage = null): void
	{
		$em = self::$em;

		$query = $em->createQuery($dql);

		$typeBuilder = new QueryResultTypeBuilder();

		if ($expectedExceptionMessage !== null) {
			$this->expectException(Throwable::class);
			$this->expectExceptionMessage($expectedExceptionMessage);
		} elseif ($expectedDeprecationMessage !== null) {
			$this->expectDeprecation();
			$this->expectDeprecationMessage($expectedDeprecationMessage);
		}

		QueryResultTypeWalker::walk($query, $typeBuilder, $this->descriptorRegistry);

		$type = $typeBuilder->getResultType();

		self::assertSame(
			$expectedType->describe(VerbosityLevel::precise()),
			$type->describe(VerbosityLevel::precise())
		);

		// Double-check our expectations

		$query = $em->createQuery($dql);

		$result = $query->getResult();
		self::assertGreaterThan(0, count($result));

		foreach ($result as $row) {
			$rowType = ConstantTypeHelper::getTypeFromValue($row);
			self::assertTrue(
				$type->accepts($rowType, true)->yes(),
				sprintf(
					"The inferred type\n%s\nshould accept actual type\n%s",
					$type->describe(VerbosityLevel::precise()),
					$rowType->describe(VerbosityLevel::precise())
				)
			);
		}
	}

	/**
	 * @return iterable<string,array{Type,string,2?:string|null}>
	 */
	abstract public function getTestData(): iterable;

	/**
	 * @param array<int,array{0: ConstantIntegerType|ConstantStringType, 1: Type, 2?: bool}> $elements
	 */
	protected function constantArray(array $elements): Type
	{
		$builder = ConstantArrayTypeBuilder::createEmpty();

		foreach ($elements as $element) {
			$offsetType = $element[0];
			$valueType = $element[1];
			$optional = $element[2] ?? false;
			$builder->setOffsetValueType($offsetType, $valueType, $optional);
		}

		return $builder->getArray();
	}

	protected function numericStringOrInt(): Type
	{
		return new UnionType([
			new IntegerType(),
			new IntersectionType([
				new StringType(),
				new AccessoryNumericStringType(),
			]),
		]);
	}

	protected function numericString(): Type
	{
		return new IntersectionType([
			new StringType(),
			new AccessoryNumericStringType(),
		]);
	}

	protected function uint(): Type
	{
		return IntegerRangeType::fromInterval(0, null);
	}

	protected function intStringified(): Type
	{
		return TypeCombinator::union(
			new IntegerType(),
			$this->numericString()
		);
	}

	protected function uintStringified(): Type
	{
		return TypeCombinator::union(
			$this->uint(),
			$this->numericString()
		);
	}

	protected function numericStringified(): Type
	{
		return TypeCombinator::union(
			new FloatType(),
			new IntegerType(),
			$this->numericString()
		);
	}

	protected function unumericStringified(): Type
	{
		return TypeCombinator::union(
			new FloatType(),
			IntegerRangeType::fromInterval(0, null),
			$this->numericString()
		);
	}

	protected function hasTypedExpressions(): bool
	{
		return class_exists(TypedExpression::class);
	}

	/**
	 * @param array<mixed[]> $arrays
	 *
	 * @return iterable<mixed[]>
	 */
	private static function combinations(array $arrays): iterable
	{
		if ($arrays === []) {
			yield [];
			return;
		}

		$head = array_shift($arrays);

		foreach ($head as $elem) {
			foreach (self::combinations($arrays) as $combination) {
				yield array_merge([$elem], $combination);
			}
		}
	}

	protected function isDoctrine211(): bool
	{
		$version = InstalledVersions::getVersion('doctrine/orm');

		return $version !== null
			&& version_compare($version, '2.11', '>=')
			&& version_compare($version, '2.12', '<');
	}

}
