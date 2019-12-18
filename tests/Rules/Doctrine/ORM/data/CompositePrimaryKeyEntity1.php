<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class CompositePrimaryKeyEntity1
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="bigint", nullable=true)
	 * @var string
	 */
	private $id;

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="string")
	 * @var string|null
	 */
	private $country;

}
