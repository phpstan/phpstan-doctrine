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

}
