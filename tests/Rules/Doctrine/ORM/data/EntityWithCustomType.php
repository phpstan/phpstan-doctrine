<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithCustomType
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="int")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Column(type="custom")
	 * @var int
	 */
	private $foo;
}
