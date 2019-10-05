<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
abstract class MyBrokenSuperclass
{

	/**
	 * @ORM\Column(type="binary")
	 * @var int
	 */
	private $five;

}
