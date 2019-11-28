<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MyEntityRelationNullablePrimaryKey
{
	/**
	 * @ORM\Id()
	 * @ORM\ManyToOne(targetEntity=MyEntity::class)
	 *
	 * @var MyEntity|null
	 */
	private $id;

}
