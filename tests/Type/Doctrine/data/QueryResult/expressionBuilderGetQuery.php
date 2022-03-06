<?php declare(strict_types = 1);

namespace QueryResult\CreateQuery;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use QueryResult\Entities\Many;
use function PHPStan\Testing\assertType;

class ExpressionBuilderGetQuery
{
	public function isNullLiteralString(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->isNull('field');
		assertType('literal-string&non-empty-string', $result);
	}

	public function isNullNonLiteralString(EntityManagerInterface $em): void
	{
		$field = strtolower('field'); // Non literal-string, e.g. $_POST['field'];
		$result = $em->createQueryBuilder()->expr()->isNull($field);
		assertType('string', $result);
	}

	public function isNotNullLiteralString(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->isNotNull('field');
		assertType('literal-string&non-empty-string', $result);
	}

	public function isNotNullNonLiteralString(EntityManagerInterface $em): void
	{
		$field = strtolower('field'); // Non literal-string, e.g. $_POST['field'];
		$result = $em->createQueryBuilder()->expr()->isNotNull($field);
		assertType('string', $result);
	}

	public function countDistinctLiteralString(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->countDistinct('A', 'B', 'C');
		assertType('literal-string&non-empty-string', $result);
	}

	public function countDistinctNonLiteralString(EntityManagerInterface $em): void
	{
		$field = strtolower('B'); // Non literal-string, e.g. $_POST['field'];
		$result = $em->createQueryBuilder()->expr()->countDistinct('A', $field, 'C');
		assertType('string', $result);
	}

	public function betweenLiteralString(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->between('field', "'value_1'", "'value_2'");
		assertType('literal-string&non-empty-string', $result);
	}

	public function betweenNonLiteralString(EntityManagerInterface $em): void
	{
		$value_1 = strtolower('B'); // Non literal-string, e.g. $_POST['field'];
		$result = $em->createQueryBuilder()->expr()->between('field', "'" . $value_1 . "'", "'value_2'");
		assertType('string', $result);
	}

	// Might be a problem, as these do not return a 'literal-string'.
	// As in, functions to support MOD() and ABS() return stringable value objects (Expr\Func).
	public function isNullNonLiteralStringExprFunc(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->isNull($qb->expr()->mod('field', '0'));
		assertType('string', $result);
	}

	public function betweenNonLiteralStringExprFunc(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->between($qb->expr()->abs('field'), '10', '30');
		assertType('string', $result);
	}

}
