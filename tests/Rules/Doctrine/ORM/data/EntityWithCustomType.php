<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class EntityWithCustomType
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	private $id;

	/**
	 * @ORM\Column(type="custom")
	 * @var int
	 */
	#[ORM\Column(type: 'custom')]
	private $foo;

	/**
	 * @ORM\Column(type="custom_numeric")
	 * @var string
	 */
	#[ORM\Column(type: 'custom_numeric')]
	private $numeric;

	/**
	 * @ORM\Column(type="custom_numeric")
	 * @var numeric-string
	 */
	#[ORM\Column(type: 'custom_numeric')]
	private $correctNumeric;
}
