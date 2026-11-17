<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\OrderBy;
use SortDirection;

class TestQueryBuilderSortDirectionRepository
{

	/** @var EntityManager */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function orderByUnknownField(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->orderBy('e.name', SortDirection::Ascending)
			->getQuery();
	}

	public function orderByCorrect(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->orderBy('e.title', SortDirection::Descending)
			->getQuery();
	}

	public function addOrderByUnknownField(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->orderBy('e.title', SortDirection::Ascending)
			->addOrderBy('e.name', SortDirection::Descending)
			->getQuery();
	}

	public function newExprUnknownField(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->add('orderBy', new OrderBy('e.name', SortDirection::Descending))
			->getQuery();
	}

	public function newExprCorrect(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->add('orderBy', new OrderBy('e.title', SortDirection::Ascending))
			->getQuery();
	}

	public function enumNotConvertibleToDql(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->where(new Comparison('e.id', '=', SortDirection::Ascending))
			->getQuery();
	}

	public function enumNotAcceptedByConstructor(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->add('from', new \Doctrine\ORM\Query\Expr\From(SortDirection::Ascending, 'e'))
			->getQuery();
	}

}
