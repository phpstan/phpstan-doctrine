<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManager;

class TestQueryBuilderRepository
{

	/** @var EntityManager */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * @return MyEntity[]
	 */
	public function getEntities(): array
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->getQuery();
	}

	public function parseError(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.id = 1)')
			->getQuery();
	}

	public function parseErrorNonFluent(int $id): void
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb = $qb->select('e');
		$qb = $qb->from(MyEntity::class, 'e');
		$qb->andWhere('e.id = :id)')
			->setParameter('id', $id)
			->getQuery();
	}

	public function parseErrorStateful(int $id): void
	{
		$qb = $this->entityManager->createQueryBuilder();
		$qb->select('e');
		$qb->from(MyEntity::class, 'e');
		$qb->andWhere('e.id = :id)');
		$qb->setParameters(['id' => $id]);
		$qb->getQuery();
	}

	// todo if/else - union of QBs

	public function unknownField(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->where('e.transient = :test')
			->getQuery();
	}

	public function unknownEntity(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from('Foo', 'e')
			->getQuery();
	}

	public function selectArray(): void
	{
		$this->entityManager->createQueryBuilder()
			->select([
				'e.id',
				'e.title',
			])->from(MyEntity::class, 'e')
			->getQuery();
	}

	public function analyseQueryBuilderUnknownBeginning(): void
	{
		$this->createQb()->getQuery();
	}

	private function createQb(): \Doctrine\ORM\QueryBuilder
	{
		return $this->entityManager->createQueryBuilder();
	}

	public function analyseQueryBuilderDynamicArgs(string $entity): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from($entity, 'e')
			->getQuery();
	}

	public function limitOffset(int $offset, int $limit): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.transient = 1')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->getQuery();
	}

	public function limitOffsetCorrect(int $offset, int $limit): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.id = 1')
			->setFirstResult($offset)
			->setMaxResults($limit)
			->getQuery();
	}

	public function addNewExprSyntaxError(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.id = 1')
			->add('orderBy', new \Doctrine\ORM\Query\Expr\OrderBy('e.name)', 'ASC'))
			->getQuery();
	}

	public function addNewExprSemanticError(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.id = 1')
			->add('orderBy', new \Doctrine\ORM\Query\Expr\OrderBy('e.name', 'ASC'))
			->getQuery();
	}

	public function addNewExprCorrect(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.id = 1')
			->add('orderBy', new \Doctrine\ORM\Query\Expr\OrderBy('e.title', 'ASC'))
			->getQuery();
	}

	public function addNewExprFirstAssignedToVariable(): void
	{
		$orderBy = new \Doctrine\ORM\Query\Expr\OrderBy('e.name', 'ASC');
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->andWhere('e.id = 1')
			->add('orderBy', $orderBy)
			->getQuery();
	}

	public function addNewExprBase(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->add('where', new \Doctrine\ORM\Query\Expr\Andx([
				'e.transient = 1',
				'e.name = \'foo\'',
			]))
			->getQuery();
	}

	public function addNewExprBaseCorrect(): void
	{
		$this->entityManager->createQueryBuilder()
			->select('e')
			->from(MyEntity::class, 'e')
			->add('where', new \Doctrine\ORM\Query\Expr\Andx([
				'e.id = 1',
			]))
			->getQuery();
	}

	public function qbExpr(): void
	{
		$queryBuilder = $this->entityManager->createQueryBuilder();
		$queryBuilder->select('e')
			->from(MyEntity::class, 'e')
			->add('where', $queryBuilder->expr()->orX(
				$queryBuilder->expr()->eq('e.id', '1'),
				$queryBuilder->expr()->like('e.nickname', '\'nick\'')
			))
			->getQuery();
	}

}
