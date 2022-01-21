<?php declare(strict_types=1);

namespace PHPStan\DoctrineIntegration\ORM\DbalQueryBuilderExecuteDynamicReturn;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class Example
{
	/** @var Connection */
	private $connection;

	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	/**
	 * @return mixed[]
	 */
	public function testCaseOne(int $userId): array
	{
		return $this->connection->createQueryBuilder()
			->select('*')
			->from('user')
			->where('user.id = :id')
			->setParameter('id', $userId)
			->execute()
			->fetchAll();
	}

	/**
	 * @return mixed[]
	 */
	public function testCaseTwo(int $userId): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*');
		$qb->from('user');
		$qb->where('user.id = :id');
		$qb->setParameter('id', $userId);

		return $qb->execute()->fetchAll();
	}

	/**
	 * @return mixed[]
	 */
	public function testCaseThree(?int $userId = null): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*');
		$qb->from('user');

		if ($userId !== null) {
			$qb->where('user.id = :id');
			$qb->setParameter('id', $userId);
		}

		return $qb->execute()->fetchAll();
	}

	/**
	 * @return mixed[]
	 */
	public function testCaseFourA(?int $userId = null): array
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*');
		$qb->from('user');

		if ($userId !== null) {
			return $this->testCaseFourB($qb, $userId);
		}

		return $qb->execute()->fetchAll();
	}

	/**
	 * @return mixed[]
	 */
	private function testCaseFourB(QueryBuilder $qb, int $userId): array
	{
		$qb->where('user.id = :id');
		$qb->setParameter('id', $userId);

		return $qb->execute()->fetchAll();
	}
}
