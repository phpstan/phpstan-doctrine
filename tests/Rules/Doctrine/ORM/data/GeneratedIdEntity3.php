<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class GeneratedIdEntity3
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="bigint")
	 * @var string|null
	 */
	private $id;

}
