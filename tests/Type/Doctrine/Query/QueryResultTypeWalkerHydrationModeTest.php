<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
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
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use QueryResult\Entities\Simple;
use Type\Doctrine\data\QueryResult\CustomIntType;
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
	public function test(Type $expectedType, string $dql, string $methodName, ?int $hydrationMode = null): void
	{
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
			self::getContainer()->getByType(DriverDetector::class),
		);

		$resolver = self::getContainer()->getByType(HydrationModeReturnTypeResolver::class);

		$type = $resolver->getMethodReturnTypeForHydrationMode(
			$methodName,
			new ConstantIntegerType($this->getRealHydrationMode($methodName, $hydrationMode)),
			$typeBuilder->getIndexType(),
			$typeBuilder->getResultType(),
			$entityManager,
		) ?? new MixedType();

		self::assertSame(
			$expectedType->describe(VerbosityLevel::precise()),
			$type->describe(VerbosityLevel::precise()),
		);

		$query = $entityManager->createQuery($dql);
		$result = $this->getQueryResult($query, $methodName, $hydrationMode);

		$resultType = ConstantTypeHelper::getTypeFromValue($result);
		self::assertTrue(
			$type->accepts($resultType, true)->yes(),
			sprintf(
				"The inferred type\n%s\nshould accept actual type\n%s",
				$type->describe(VerbosityLevel::precise()),
				$resultType->describe(VerbosityLevel::precise()),
			),
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

		yield 'getResult(simple_object), full entity' => [
			self::list(new ObjectType(Simple::class)),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_SIMPLEOBJECT,
		];

		yield 'getResult(object), fields' => [
			self::list(self::constantArray([
				[new ConstantStringType('decimalColumn'), self::numericString()],
				[new ConstantStringType('floatColumn'), new FloatType()],
			])),
			'
				SELECT		s.decimalColumn, s.floatColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_OBJECT,
		];

		yield 'getResult(object), expressions' => [
			self::list(self::constantArray([
				[new ConstantStringType('decimalColumn'), self::floatOrIntOrStringified()],
				[new ConstantStringType('floatColumn'), self::floatOrStringified()],
			])),
			'
				SELECT		-s.decimalColumn as decimalColumn, -s.floatColumn as floatColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_OBJECT,
		];

		yield 'toIterable(object), full entity' => [
			new IterableType(new IntegerType(), new ObjectType(Simple::class)),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'toIterable',
			Query::HYDRATE_OBJECT,
		];

		yield 'toIterable(object), fields' => [
			new IterableType(new IntegerType(), self::constantArray([
				[new ConstantStringType('decimalColumn'), self::numericString()],
				[new ConstantStringType('floatColumn'), new FloatType()],
			])),
			'
				SELECT		s.decimalColumn, s.floatColumn
				FROM		QueryResult\Entities\Simple s
			',
			'toIterable',
			Query::HYDRATE_OBJECT,
		];

		yield 'toIterable(object), expressions' => [
			new IterableType(new IntegerType(), self::constantArray([
				[new ConstantStringType('decimalColumn'), self::floatOrIntOrStringified()],
				[new ConstantStringType('floatColumn'), self::floatOrStringified()],
			])),
			'
				SELECT		-s.decimalColumn as decimalColumn, -s.floatColumn as floatColumn
				FROM		QueryResult\Entities\Simple s
			',
			'toIterable',
			Query::HYDRATE_OBJECT,
		];

		yield 'toIterable(simple_object), full entity' => [
			new IterableType(new IntegerType(), new ObjectType(Simple::class)),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'toIterable',
			Query::HYDRATE_SIMPLEOBJECT,
		];

		yield 'getArrayResult(), full entity' => [
			new MixedType(),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getArrayResult',
		];

		yield 'getResult(single_scalar), decimal field' => [
			new MixedType(),
			'
				SELECT		s.decimalColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
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

		yield 'getResult(scalar), decimal field' => [
			new MixedType(),
			'
				SELECT		s.decimalColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getResult',
			Query::HYDRATE_SCALAR,
		];

		yield 'getScalarResult, full entity' => [
			new MixedType(),
			'
				SELECT		s
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
		];

		yield 'getScalarResult(), decimal field' => [
			new MixedType(),
			'
				SELECT		s.decimalColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
		];

		yield 'getScalarResult(), decimal expression' => [
			new MixedType(),
			'
				SELECT		-s.decimalColumn as col
				FROM		QueryResult\Entities\Simple s
			',
			'getScalarResult',
		];

		yield 'getSingleScalarResult(), decimal field' => [
			new MixedType(),
			'
				SELECT		s.decimalColumn
				FROM		QueryResult\Entities\Simple s
			',
			'getSingleScalarResult',
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

	/**
	 * @param Query<mixed, mixed> $query
	 * @return mixed
	 */
	private function getQueryResult(Query $query, string $methodName, ?int $hydrationMode)
	{
		if ($methodName === 'getResult') {
			if ($hydrationMode === null) {
				throw new LogicException('Hydration mode must be set for getResult() method.');
			}
			return $query->getResult($hydrationMode);  // @phpstan-ignore-line dynamic arg
		}

		if ($methodName === 'getArrayResult') {
			if ($hydrationMode !== null) {
				throw new LogicException('Hydration mode must NOT be set for getArrayResult() method.');
			}
			return $query->getArrayResult();
		}

		if ($methodName === 'getScalarResult') {
			if ($hydrationMode !== null) {
				throw new LogicException('Hydration mode must NOT be set for getScalarResult() method.');
			}
			return $query->getScalarResult();
		}

		if ($methodName === 'getSingleResult') {
			if ($hydrationMode === null) {
				throw new LogicException('Hydration mode must be set for getSingleResult() method.');
			}

			return $query->getSingleResult($hydrationMode); // @phpstan-ignore-line dynamic arg
		}

		if ($methodName === 'getSingleScalarResult') {
			if ($hydrationMode !== null) {
				throw new LogicException('Hydration mode must NOT be set for getSingleScalarResult() method.');
			}

			return $query->getSingleScalarResult();
		}

		if ($methodName === 'toIterable') {
			if ($hydrationMode === null) {
				throw new LogicException('Hydration mode must be set for toIterable() method.');
			}

			return $query->toIterable([], $hydrationMode);  // @phpstan-ignore-line dynamic arg
		}

		throw new LogicException(sprintf('Unsupported method %s.', $methodName));
	}

	private function getRealHydrationMode(string $methodName, ?int $hydrationMode): int
	{
		if ($hydrationMode !== null) {
			return $hydrationMode;
		}

		if ($methodName === 'getArrayResult') {
			return Query::HYDRATE_ARRAY;
		}

		if ($methodName === 'getScalarResult') {
			return Query::HYDRATE_SCALAR;
		}

		if ($methodName === 'getSingleScalarResult') {
			return Query::HYDRATE_SCALAR;
		}

		throw new LogicException(sprintf('Using %s without hydration mode is not supported.', $methodName));
	}


	private static function stringifies(): bool
	{
		return PHP_VERSION_ID < 80100;
	}

	private static function floatOrStringified(): Type
	{
		return self::stringifies()
			? self::numericString()
			: new FloatType();
	}

	private static function floatOrIntOrStringified(): Type
	{
		return self::stringifies()
			? self::numericString()
			: TypeCombinator::union(new FloatType(), new IntegerType());
	}

}
