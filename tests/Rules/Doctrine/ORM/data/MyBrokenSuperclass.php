<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
#[ORM\MappedSuperclass]
abstract class MyBrokenSuperclass
{

	/**
	 * @ORM\Column(type="binary")
	 * @var int
	 */
	#[ORM\Column(type: 'binary')]
	private $five;

}
