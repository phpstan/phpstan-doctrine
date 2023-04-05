<?php

namespace QueryBuilderReproductions;

use Doctrine\ORM\EntityManager;
use QueryResult\Entities\OrderItem;
use function PHPStan\Testing\assertType;

class Foo
{

	/** @var EntityManager */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function doFoo(): void
	{
		$result = $this->entityManager->createQueryBuilder()
			->select('DISTINCT IDENTITY(oi.product) AS id')
			->from(OrderItem::class, 'oi')
			->join('oi.product', 'p')
			->getQuery()
			->getArrayResult();
		assertType('list<array{id: int}>', $result);
	}

}
