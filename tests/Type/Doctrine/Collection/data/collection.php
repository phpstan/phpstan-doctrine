<?php declare(strict_types = 1);

use Doctrine\Common\Collections\ArrayCollection;

class MyEntity
{

}

$new = new MyEntity();

/**
 * @var ArrayCollection<int, MyEntity> $collection
 */
$collection = new ArrayCollection();

$entityOrFalse = $collection->first();
$entityOrFalse;

if ($collection->isEmpty()) {
	$false = $collection->first();
	$false;
}

if (!$collection->isEmpty()) {
	$entity = $collection->first();
	$entity;
}
