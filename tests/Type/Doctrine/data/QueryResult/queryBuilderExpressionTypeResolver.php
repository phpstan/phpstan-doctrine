<?php declare(strict_types = 1);  // lint >= 8.1

namespace QueryResult\CreateQuery;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\From;
use Doctrine\ORM\QueryBuilder;
use QueryResult\Entities\Many;
use function PHPStan\Testing\assertType;

class QueryBuilderExpressionTypeResolverTest
{

	public function testQueryTypeIsInferredOnAcrossMethods(EntityManagerInterface $em): void
	{
		$query = $this->getQueryBuilder($em)->getQuery();
		$branchingQuery = $this->getBranchingQueryBuilder($em)->getQuery();

		assertType('Doctrine\ORM\Query<null, QueryResult\Entities\Many>', $query);
		assertType('Doctrine\ORM\Query<null, QueryResult\Entities\Many>', $branchingQuery);
	}

	public function testQueryTypeIsInferredOnAcrossMethodsEvenWhenVariableAssignmentIsUsed(EntityManagerInterface $em): void
	{
		$queryBuilder = $this->getQueryBuilder($em);

		assertType('Doctrine\ORM\Query<null, QueryResult\Entities\Many>', $queryBuilder->getQuery());
	}

	public function testFirstClassCallableDoesNotFail(EntityManagerInterface $em): void
	{
		$this->getQueryBuilder(...);
	}

	private function getQueryBuilder(EntityManagerInterface $em): QueryBuilder
	{
		return $em->createQueryBuilder()
			->select('m')
			->from(Many::class, 'm');
	}

	private function getBranchingQueryBuilder(EntityManagerInterface $em): QueryBuilder
	{
		$queryBuilder = $em->createQueryBuilder()
			->select('m')
			->from(Many::class, 'm');

		if (random_int(0, 1) === 1) {
			$queryBuilder->andWhere('m.intColumn = 1');
		}

		return $queryBuilder;
	}
}
