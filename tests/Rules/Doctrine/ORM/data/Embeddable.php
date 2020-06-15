<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
class Embeddable
{
	/**
	 * @ORM\Column(type="string")
	 * @var string
	 */
	private $one;
}
