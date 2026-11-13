<?php declare(strict_types = 1); // lint >= 8.0

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use function PHPStan\Testing\assertType;

function dynamicObjectRoot(EntityManagerInterface $em, DynamicRoot $entity): void
{
	$query = $em->createQueryBuilder()
		->select('e')
		->from($entity::class, 'e')
		->innerJoin('e.channel', 'c')
		->getQuery();

	assertType(
		'PHPStan\Rules\Doctrine\ORM\DynamicRootA|PHPStan\Rules\Doctrine\ORM\DynamicRootB',
		$query->getSingleResult(AbstractQuery::HYDRATE_OBJECT),
	);
}

/** @param class-string<DynamicRoot> $class */
function dynamicClassStringRootMissingAssociation(EntityManagerInterface $em, string $class): void
{
	$em->createQueryBuilder()
		->select('e')
		->from($class, 'e')
		->innerJoin('e.channel', 'c')
		->getQuery();
}

function dynamicRootMissingField(EntityManagerInterface $em, DynamicRoot $entity): void
{
	$em->createQueryBuilder()
		->select('e')
		->from($entity::class, 'e')
		->innerJoin('e.channel', 'c')
		->where('e.nonexistent = 1')
		->getQuery();
}

function dynamicRootMissingAssociation(EntityManagerInterface $em, PartialRoot $entity): void
{
	$em->createQueryBuilder()
		->select('e')
		->from($entity::class, 'e')
		->innerJoin('e.channel', 'c')
		->getQuery();
}

function literalAbstractRoot(EntityManagerInterface $em): void
{
	$em->createQueryBuilder()
		->select('e')
		->from(DynamicRoot::class, 'e')
		->where('e.id = 1')
		->getQuery();
}

function literalAbstractRootMissingAssociation(EntityManagerInterface $em): void
{
	$em->createQueryBuilder()
		->select('e')
		->from(DynamicRoot::class, 'e')
		->innerJoin('e.channel', 'c')
		->getQuery();
}

function reassignedDynamicRoot(EntityManagerInterface $em, DynamicRoot $first, DynamicRoot $second): void
{
	$entity = $first;
	$qb = $em->createQueryBuilder()
		->select('a', 'b')
		->from($entity::class, 'a');
	$entity = $second;
	$qb->from($entity::class, 'b');

	assertType(
		'\'SELECT a, b FROM PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootA a, PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootA b\''
		. '|\'SELECT a, b FROM PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootA a, PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootB b\''
		. '|\'SELECT a, b FROM PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootB a, PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootA b\''
		. '|\'SELECT a, b FROM PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootB a, PHPStan\\\\Rules\\\\Doctrine\\\\ORM\\\\DynamicRootB b\'',
		$qb->getQuery()->getDQL(),
	);
}

function independentDynamicRootsExceedLimit(
	EntityManagerInterface $em,
	DynamicRoot $a,
	DynamicRoot $b,
	DynamicRoot $c,
	DynamicRoot $d,
	DynamicRoot $e
): void
{
	$em->createQueryBuilder()
		->select('a', 'b', 'c', 'd', 'e')
		->from($a::class, 'a')
		->from($b::class, 'b')
		->from($c::class, 'c')
		->from($d::class, 'd')
		->from($e::class, 'e')
		->getQuery();
}
