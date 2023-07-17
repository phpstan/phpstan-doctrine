<?php declare(strict_types=1);

namespace PHPStan\DoctrineIntegration\ORM\DbalQueryBuilderExecuteDynamicReturn;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\ForwardCompatibility\Result;
use function PHPStan\Testing\assertType;

class Example
{
	/** @var Connection */
	private $connection;

	public function __construct(Connection $connection)
	{
		$this->connection = $connection;
	}

	public function testCaseOne(int $userId): void
	{
		$test = $this->connection->createQueryBuilder()
			->select('*')
			->from('user')
			->where('user.id = :id')
			->setParameter('id', $userId)
			->execute();
		assertType(Result::class, $test);
	}

	public function testCaseTwo(int $userId): void
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*');
		$qb->from('user');
		$qb->where('user.id = :id');
		$qb->setParameter('id', $userId);

		assertType(Result::class . '|int|string', $qb->execute());
	}

	public function testCaseThree(?int $userId = null): void
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*');
		$qb->from('user');

		if ($userId !== null) {
			$qb->where('user.id = :id');
			$qb->setParameter('id', $userId);
		}

		assertType(Result::class . '|int|string', $qb->execute());
	}

	public function testCaseFourA(?int $userId = null): void
	{
		$qb = $this->connection->createQueryBuilder();
		$qb->select('*');
		$qb->from('user');

		if ($userId !== null) {
			$this->testCaseFourB($qb, $userId);
		}

		assertType(Result::class . '|int|string', $qb->execute());
	}

	private function testCaseFourB(QueryBuilder $qb, int $userId): void
	{
		$qb->where('user.id = :id');
		$qb->setParameter('id', $userId);

		assertType(Result::class . '|int|string', $qb->execute());
	}
}
