<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class AnotherEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="int")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithRelations")
	 */
	private $manyToOne;

}
