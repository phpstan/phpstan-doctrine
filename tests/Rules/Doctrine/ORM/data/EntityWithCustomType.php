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
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Column(type="custom")
	 * @var int
	 */
	private $foo;

	/**
	 * @ORM\Column(type="custom_numeric")
	 * @var string
	 */
	private $numeric;

	/**
	 * @ORM\Column(type="custom_numeric")
	 * @var numeric-string
	 */
	private $correctNumeric;
}
