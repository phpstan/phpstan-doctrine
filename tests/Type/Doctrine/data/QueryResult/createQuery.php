<?php declare(strict_types = 1);

namespace QueryResult\CreateQuery;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use function PHPStan\Testing\assertType;

class CreateQuery
{
	public function testQueryTypeParametersAreInfered(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('
			SELECT		m
			FROM		QueryResult\Entities\Many m
		');

		assertType('Doctrine\ORM\Query<null, QueryResult\Entities\Many, 1>', $query);

		$query = $em->createQuery('
			SELECT		m.intColumn, m.stringNullColumn
			FROM		QueryResult\Entities\Many m
		');

		assertType('Doctrine\ORM\Query<null, array{intColumn: int, stringNullColumn: string|null}, 1>', $query);
	}

	public function testQueryTypeSimpleArray(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('SELECT m.simpleArrayColumn FROM QueryResult\Entities\Many m');

		assertType('Doctrine\ORM\Query<null, array{simpleArrayColumn: list<string>}, 1>', $query);
	}

	public function testMappingError(EntityManagerInterface $em): void
	{
		$query = $em->createQuery('SELECT u.foo FROM ' . CreateQuery::class . ' u');

		assertType('Doctrine\ORM\Query<mixed, mixed, 1>', $query);
	}

	public function testQueryResultTypeIsMixedWhenDQLIsNotKnown(EntityManagerInterface $em, string $dql): void
	{
		$query = $em->createQuery($dql);

		assertType('Doctrine\ORM\Query<mixed, mixed, mixed>', $query);
	}

	public function testQueryResultTypeIsMixedWhenDQLIsInvalid(EntityManagerInterface $em, string $dql): void
	{
		$query = $em->createQuery('invalid');

		assertType('Doctrine\ORM\Query<mixed, mixed, 1>', $query);
	}

}
