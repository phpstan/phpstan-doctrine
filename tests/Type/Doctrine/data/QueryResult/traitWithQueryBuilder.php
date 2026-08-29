<?php declare(strict_types = 1);

namespace QueryResult\CreateQuery;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use QueryResult\Entities\Many;

trait TraitWithQueryBuilder
{
	public function getQueryBuilderFromTrait(EntityManagerInterface $em): QueryBuilder
	{
		return $em->createQueryBuilder()
			->select('m')
			->from(Many::class, 'm');
	}

}
