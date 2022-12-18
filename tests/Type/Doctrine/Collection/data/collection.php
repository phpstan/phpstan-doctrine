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

$entityOrFalse1 = $collection->first();
$entityOrFalse1;

$entityOrFalse2 = $collection->last();
$entityOrFalse2;

if ($collection->isEmpty()) {
	$false1 = $collection->first();
	$false1;

	$false2 = $collection->last();
	$false2;

	$collection->add($new);
	$result1 = $collection->first();
	$result1;

	$result2 = $collection->last();
	$result2;
}

if (!$collection->isEmpty()) {
	$entity1 = $collection->first();
	$entity1;

	$entity2 = $collection->last();
	$entity2;

	$collection->removeElement($new);
	$result3 = $collection->first();
	$result3;

	$result4 = $collection->last();
	$result4;
}
