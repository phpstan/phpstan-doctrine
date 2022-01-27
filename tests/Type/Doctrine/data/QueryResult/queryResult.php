<?php declare(strict_types = 1);

namespace QueryResult\queryResult;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use QueryResult\Entities\Many;
use function PHPStan\Testing\assertType;

class QueryResultTest
{
	public function testQueryTypeParametersAreInfered(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		m
			FROM		QueryResult\Entities\Many m
		');

		assertType('Doctrine\ORM\Query<QueryResult\Entities\Many>', $query);

		$query = $em->createQuery('
			SELECT		m.intColumn, m.stringNullColumn
			FROM		QueryResult\Entities\Many m
		');

		assertType('Doctrine\ORM\Query<array{intColumn: int, stringNullColumn: string|null}>', $query);

	}

	/**
	 * Test that we properly infer the return type of Query methods with implicit hydration mode
	 *
	 * - getResult() has a default hydration mode of HYDRATE_OBJECT, so we are able to infer the return type
	 * - Other methods have a default hydration mode of null and fallback on AbstractQuery::getHydrationMode(), so we can not assume the hydration mode and can not infer the return type
	 */
	public function testReturnTypeOfQueryMethodsWithImplicitHydrationMode(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		m
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'array<QueryResult\Entities\Many>',
			$query->getResult()
		);
		assertType(
			'mixed',
			$query->execute()
		);
		assertType(
			'mixed',
			$query->executeIgnoreQueryCache()
		);
		assertType(
			'mixed',
			$query->executeUsingQueryCache()
		);
		assertType(
			'mixed',
			$query->getSingleResult()
		);
		assertType(
			'mixed',
			$query->getOneOrNullResult()
		);
	}

	/**
	 * Test that we properly infer the return type of Query methods with explicit hydration mode of HYDRATE_OBJECT
	 *
	 * We are able to infer the return type in most cases here
	 */
	public function testReturnTypeOfQueryMethodsWithExplicitObjectHydrationMode(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		m
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'array<QueryResult\Entities\Many>',
			$query->getResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<QueryResult\Entities\Many>',
			$query->execute(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<QueryResult\Entities\Many>',
			$query->executeIgnoreQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<QueryResult\Entities\Many>',
			$query->executeUsingQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'QueryResult\Entities\Many',
			$query->getSingleResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'QueryResult\Entities\Many|null',
			$query->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT)
		);

		$query = $em->createQuery('
			SELECT		m.intColumn, m.stringNullColumn
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'array<array{intColumn: int, stringNullColumn: string|null}>',
			$query->getResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<array{intColumn: int, stringNullColumn: string|null}>',
			$query->execute(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<array{intColumn: int, stringNullColumn: string|null}>',
			$query->executeIgnoreQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<array{intColumn: int, stringNullColumn: string|null}>',
			$query->executeUsingQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array{intColumn: int, stringNullColumn: string|null}',
			$query->getSingleResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array{intColumn: int, stringNullColumn: string|null}|null',
			$query->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT)
		);
	}

	/**
	 * Test that we properly infer the return type of Query methods with explicit hydration mode that is not HYDRATE_OBJECT
	 *
	 * We are never able to infer the return type here
	 */
	public function testReturnTypeOfQueryMethodsWithExplicitNonObjectHydrationMode(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		m
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'mixed',
			$query->getResult(AbstractQuery::HYDRATE_ARRAY)
		);
		assertType(
			'mixed',
			$query->execute(null, AbstractQuery::HYDRATE_ARRAY)
		);
		assertType(
			'mixed',
			$query->executeIgnoreQueryCache(null, AbstractQuery::HYDRATE_ARRAY)
		);
		assertType(
			'mixed',
			$query->executeUsingQueryCache(null, AbstractQuery::HYDRATE_ARRAY)
		);
		assertType(
			'mixed',
			$query->getSingleResult(AbstractQuery::HYDRATE_ARRAY)
		);
		assertType(
			'mixed',
			$query->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY)
		);
	}

	/**
	 * Test that we properly infer the return type of Query methods with explicit hydration mode that is not a constant value
	 *
	 * We are never able to infer the return type here
	 *
	 * @param int AbstractQuery::HYDRATE_*
	 */
	public function testReturnTypeOfQueryMethodsWithExplicitNonConstantHydrationMode(EntityManagerInterface $em, int $hydrationMode): void
	{
		$query = $em->createQuery('
			SELECT		m
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'mixed',
			$query->getResult($hydrationMode)
		);
		assertType(
			'mixed',
			$query->execute(null, $hydrationMode)
		);
		assertType(
			'mixed',
			$query->executeIgnoreQueryCache(null, $hydrationMode)
		);
		assertType(
			'mixed',
			$query->executeUsingQueryCache(null, $hydrationMode)
		);
		assertType(
			'mixed',
			$query->getSingleResult($hydrationMode)
		);
		assertType(
			'mixed',
			$query->getOneOrNullResult($hydrationMode)
		);
	}

	/**
	 * Test that we return the original return type when ResultType may be
	 * VoidType
	 *
	 * @param Query<mixed> $query
	 */
	public function testReturnTypeOfQueryMethodsWithReturnTypeIsMixed(EntityManagerInterface $em, Query $query): void
	{
		assertType(
			'mixed',
			$query->getResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->execute(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->executeIgnoreQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->executeUsingQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->getSingleResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT)
		);
	}

	/**
	 * Test that we return the original return type when ResultType may be
	 * VoidType (TemplateType variant)
	 *
	 * @template T
	 *
	 * @param Query<T> $query
	 */
	public function testReturnTypeOfQueryMethodsWithReturnTypeIsTemplateMixedType(EntityManagerInterface $em, Query $query): void
	{
		assertType(
			'mixed',
			$query->getResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->execute(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->executeIgnoreQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->executeUsingQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->getSingleResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'mixed',
			$query->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT)
		);
	}


	/**
	 * Test that we return ResultType return ResultType can not be VoidType
	 *
	 * @template T of array|object
	 *
	 * @param Query<T> $query
	 */
	public function testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(EntityManagerInterface $em, Query $query): void
	{
		assertType(
			'array<T of array|object (method QueryResult\queryResult\QueryResultTest::testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(), argument)>',
			$query->getResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<T of array|object (method QueryResult\queryResult\QueryResultTest::testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(), argument)>',
			$query->execute(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<T of array|object (method QueryResult\queryResult\QueryResultTest::testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(), argument)>',
			$query->executeIgnoreQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'array<T of array|object (method QueryResult\queryResult\QueryResultTest::testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(), argument)>',
			$query->executeUsingQueryCache(null, AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'T of array|object (method QueryResult\queryResult\QueryResultTest::testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(), argument)',
			$query->getSingleResult(AbstractQuery::HYDRATE_OBJECT)
		);
		assertType(
			'(T of array|object (method QueryResult\queryResult\QueryResultTest::testReturnTypeOfQueryMethodsWithReturnTypeIsNonVoidTemplate(), argument))|null',
			$query->getOneOrNullResult(AbstractQuery::HYDRATE_OBJECT)
		);
	}

	public function testReturnTypeOfMixedResult(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		m.intColumn, m
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'array<array{0: QueryResult\Entities\Many, intColumn: int}>',
			$query->getResult()
		);
		assertType(
			'array{0: QueryResult\Entities\Many, intColumn: int}|null',
			$query->getOneOrNullResult()
		);
	}

	public function testReturnTypeOfAggregateFunctions(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		COUNT(m) AS count, COUNT(DISTINCT m) AS count_distinct, SUM(m.intColumn) AS sum, AVG(m.intColumn) AS avg
			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'array<array{count: int<0, max>|numeric-string, count_distinct: int<0, max>|numeric-string, sum: int|numeric-string|null, avg: int|numeric-string|null}>',
			$query->getResult()
		);
		assertType(
			'array{count: int<0, max>|numeric-string, count_distinct: int<0, max>|numeric-string, sum: int|numeric-string|null, avg: int|numeric-string|null}|null',
			$query->getOneOrNullResult()
		);
	}

	public function testReturnTypeOfDqlFunctions(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT
						CONCAT(m.stringColumn, COALESCE(m.stringNullColumn, m.stringColumn)) AS concat_coalesce,
						CASE
							WHEN m.stringNullColumn IS NULL
							THEN m.intColumn
							ELSE 1
						END AS case_int,
						LENGTH(m.stringColumn) AS length_int,
						SUBSTRING(m.stringColumn, 0, 1) AS substring_string,
						LOWER(m.stringColumn) AS lower_string,
						UPPER(m.stringColumn) AS upper_string,
						IDENTITY(m.one) AS identity_string

			FROM		QueryResult\Entities\Many m
		');

		assertType(
			'array<array{concat_coalesce: string, case_int: int|numeric-string, length_int: int<0, max>|numeric-string, substring_string: string, lower_string: string, upper_string: string, identity_string: mixed}>',
			$query->getResult()
		);
		assertType(
			'array{concat_coalesce: string, case_int: int|numeric-string, length_int: int<0, max>|numeric-string, substring_string: string, lower_string: string, upper_string: string, identity_string: mixed}|null',
			$query->getOneOrNullResult()
		);
	}

	public function testReturnFieldTypesWithQueryBuilderAndDifferentSelectMethods(EntityManagerInterface $em): void
	{
		$query = $em->createQueryBuilder()
			->addSelect('
				m.id,
				m.intColumn,
				m.stringColumn AS stringColumnAlias
			')
			->addSelect('o.stringNullColumn')
			->addSelect([
				'm.datetimeColumn',
				'm.datetimeImmutableColumn',
			])
			->from(Many::class, 'm')
			->join('m.one', 'o');

		assertType(
			'array<array{id: string, intColumn: int, stringColumnAlias: string, stringNullColumn: string|null, datetimeColumn: DateTime, datetimeImmutableColumn: DateTimeImmutable}>',
			$query->getQuery()->getResult()
		);
		assertType(
			'array{id: string, intColumn: int, stringColumnAlias: string, stringNullColumn: string|null, datetimeColumn: DateTime, datetimeImmutableColumn: DateTimeImmutable}|null',
			$query->getQuery()->getOneOrNullResult()
		);
	}
}
