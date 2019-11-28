<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MyEntityRelationPrimaryKey
{
	/**
	 * @ORM\Id()
	 * @ORM\ManyToOne(targetEntity=MyEntity::class)
	 *
	 * @var MyEntity
	 */
	private $id;

}
