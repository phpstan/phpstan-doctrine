<?php declare(strict_types = 1);

namespace QueryResult\CreateQuery;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use QueryResult\Entities\Many;
use function PHPStan\Testing\assertType;

class ExpressionBuilderGetQueryNoObjectManager
{
	private function nonLiteralString(string $value): string {
		return $value; // Using the 'string' return type to provide a non `literal-string`, e.g. $_POST['field'];
	}

	public function isNullLiteralString(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->isNull('field');
		assertType('literal-string&non-empty-string', $result);
	}

	public function isNullNonLiteralString(EntityManagerInterface $em): void
	{
		$field = $this->nonLiteralString('field');
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
		$field = $this->nonLiteralString('field');
		$result = $em->createQueryBuilder()->expr()->isNotNull($field);
		assertType('string', $result);
	}

	public function betweenLiteralString(EntityManagerInterface $em): void
	{
		$result = $em->createQueryBuilder()->expr()->between('field', "'value_1'", "'value_2'");
		assertType('literal-string&non-empty-string', $result);
	}

	public function betweenNonLiteralString1(EntityManagerInterface $em): void
	{
		$value = $this->nonLiteralString('A');
		$result = $em->createQueryBuilder()->expr()->between($value, "'value_1'", "'value_2'");
		assertType('string', $result);
	}

	public function betweenNonLiteralString2(EntityManagerInterface $em): void
	{
		$value = $this->nonLiteralString('A');
		$result = $em->createQueryBuilder()->expr()->between('field', "'" . $value . "'", "'value_2'");
		assertType('string', $result);
	}

	public function betweenNonLiteralString3(EntityManagerInterface $em): void
	{
		$value = $this->nonLiteralString('A');
		$result = $em->createQueryBuilder()->expr()->between('field', "'value_1'", "'" . $value . "'");
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
