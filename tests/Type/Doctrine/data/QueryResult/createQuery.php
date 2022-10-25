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

		assertType('Doctrine\ORM\Query<QueryResult\Entities\Many, mixed>', $query);

		$query = $em->createQuery('
			SELECT		m.intColumn, m.stringNullColumn
			FROM		QueryResult\Entities\Many m
		');

		assertType('Doctrine\ORM\Query<array{intColumn: int, stringNullColumn: string|null}, mixed>', $query);
	}

	public function testQueryResultTypeIsMixedWhenDQLIsNotKnown(EntityManagerInterface $em, string $dql): void
	{
		$query = $em->createQuery($dql);

		assertType('Doctrine\ORM\Query<mixed>', $query); // TODO fix
	}

	public function testQueryResultTypeIsMixedWhenDQLIsInvalid(EntityManagerInterface $em, string $dql): void
	{
		$query = $em->createQuery('invalid');

		assertType('Doctrine\ORM\Query<mixed, mixed>', $query);
	}

}
