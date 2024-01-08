<?php declare(strict_types = 1);

namespace QueryResult\Bug512;

use Doctrine\ORM\EntityManagerInterface;
use QueryResult\Entities\One;
use function PHPStan\Testing\assertType;

class Bug512
{
	public function test(EntityManagerInterface $em): void
	{
		$query = One::createQueryBuilder($em)->getQuery();
		assertType('Doctrine\ORM\Query<null, QueryResult\Entities\One>', $query);
		assertType('list<QueryResult\Entities\One>', $query->getResult());
	}
}
