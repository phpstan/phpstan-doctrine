<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Embeddable()
 */
class BrokenEmbeddable
{
	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var string
	 */
	private $one;
}
