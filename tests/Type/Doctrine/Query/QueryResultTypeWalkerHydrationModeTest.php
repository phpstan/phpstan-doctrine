<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\SchemaTool;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Php\PhpVersion;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\HydrationModeReturnTypeResolver;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use QueryResult\Entities\Simple;
use Type\Doctrine\data\QueryResult\CustomIntType;
use function count;
use function sprintf;
use const PHP_VERSION_ID;

final class QueryResultTypeWalkerHydrationModeTest extends PHPStanTestCase
{

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/../data/QueryResult/config.neon',
		];
	}

	/** @dataProvider getTestData */
	public function test(Type $expectedType, string $dql, string $methodName, int $hydrationMode): void
	{
		if (PHP_VERSION_ID < 80100) {
			self::markTestSkipped('Tests only non-stringified types so far.'); // TODO can be eliminated
		}

		/** @var EntityManagerInterface $entityManager */
		$entityManager = require __DIR__ . '/../data/QueryResult/entity-manager.php';

		if (!DbalType::hasType(CustomIntType::NAME)) {
			DbalType::addType(CustomIntType::NAME, CustomIntType::class);
		}

		$schemaTool = new SchemaTool($entityManager);
		$classes = $entityManager->getMetadataFactory()->getAllMetadata();
		$schemaTool->dropSchema($classes);
		$schemaTool->createSchema($classes);

		$simple = new Simple();
		$simple->id = '1';
		$simple->intColumn = 1;
		$simple->floatColumn = 0.1;
		$simple->decimalColumn = '1.1';
		$simple->stringColumn = 'foobar';
		$simple->stringNullColumn = null;

		$entityManager->persist($simple);
		$entityManager->flush();

		$query = $entityManager->createQuery($dql);

		$typeBuilder = new QueryResultTypeBuilder();

		QueryResultTypeWalker::walk(
			$query,
			$typeBuilder,
			self::getContainer()->getByType(DescriptorRegistry::class),
			self::getContainer()->getByType(PhpVersion::class),
			self::getContainer()->getByType(DriverDetector::class)
		);

		$resolver = self::getContainer()->getByType(HydrationModeReturnTypeResolver::class);

		$type = $resolver->getMethodReturnTypeForHydrationMode(
			$methodName,
			$hydrationMode,
			$typeBuilder->getIndexType(),
			$typeBuilder->getResultType(),
			$entityManager
		) ?? new MixedType();

		self::assertSame(
			$expectedType->describe(VerbosityLevel::precise()),
			$type->describe(VerbosityLevel::precise())
		);

		$query = $entityManager->createQuery($dql);
		$result = $query->$methodName($hydrationMode); // @phpstan-ignore-line TODO should be improved
		self::assertGreaterThan(0, count($result));

		$resultType = ConstantTypeHelper::getTypeFromValue($result);
		self::assertTrue(
			$type->accepts($resultType, true)->yes(),
			sprintf(
				"The inferred type\n%s\nshould accept actual type\n%s",
				$type->describe(VerbosityLevel::precise()),
				$resultType->describe(VerbosityLevel::precise())
			)
		);
	}

	/**
	 * @return iterable<string,array{Type,string,2?:string|null}>
	 */
	public static function getTestData(): iterable
	{
		AccessoryArrayListType::setListTypeEnabled(true);

		yield 'getResult(object), full entity' => [
			self::list(new ObjectType(Simple::class)),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_OBJECT,
		];

		yield 'getResult(array), full entity' => [
			new MixedType(),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_ARRAY,
		];

		yield 'getArray(), full entity' => [
			new MixedType(),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getArrayResult',
			Query::HYDRATE_ARRAY,
		];

		yield 'getResult(simple_object), full entity' => [
			self::list(new ObjectType(Simple::class)),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_SIMPLEOBJECT,
		];

		yield 'getResult(single_scalar), full entity' => [
			new MixedType(),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
			Query::HYDRATE_SINGLE_SCALAR,
		];

		yield 'getResult(scalar), full entity' => [
			new MixedType(),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_SCALAR,
		];

		yield 'getResult(scalar), bigint field' => [
			self::list(self::constantArray([
				[new ConstantStringType('id'), self::numericString()],
			])),
			'
				SELECT		s.id
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_SCALAR,
		];

		yield 'getScalarResult(), bigint field' => [
			self::list(self::constantArray([
				[new ConstantStringType('id'), self::numericString()],
			])),
			'
				SELECT		s.id
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
			Query::HYDRATE_SCALAR,
		];

		yield 'getScalarResult(), decimal field' => [
			self::list(self::constantArray([
				[new ConstantStringType('decimalColumn'), self::numericString()],
			])),
			'
				SELECT		s.decimalColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
			Query::HYDRATE_SCALAR,
		];

		yield 'getScalarResult(), bigint expression' => [
			self::list(self::constantArray([
				[new ConstantStringType('col'), new IntegerType()],
			])),
			'
				SELECT		-s.id as col
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
			Query::HYDRATE_SCALAR,
		];

		yield 'getScalarResult(), decimal expression' => [
			self::list(self::constantArray([
				[new ConstantStringType('col'), TypeCombinator::union(new IntegerType(), new FloatType())],
			])),
			'
				SELECT		-s.decimalColumn as col
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
			Query::HYDRATE_SCALAR,
		];
	}

	/**
	 * @param array<int,array{0: ConstantIntegerType|ConstantStringType, 1: Type, 2?: bool}> $elements
	 */
	private static function constantArray(array $elements): Type
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

	private static function list(Type $values): Type
	{
		return AccessoryArrayListType::intersectWith(new ArrayType(new IntegerType(), $values));
	}

	private static function numericString(): Type
	{
		return new IntersectionType([
			new StringType(),
			new AccessoryNumericStringType(),
		]);
	}

}
