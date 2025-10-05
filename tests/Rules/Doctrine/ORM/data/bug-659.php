<?php // lint >= 8.0

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MyEntity659
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @var string
	 * @ORM\Column(type="binary")
	 */
	private $binaryString;

	/**
	 * @var resource
	 * @ORM\Column(type="binary")
	 */
	private $binaryResource;
}
