<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class CompositePrimaryKeyEntity1
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="bigint", nullable=true)
	 * @var string
	 */
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'bigint', nullable: true)]
	private $id;

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="string")
	 * @var string|null
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'string')]
	private $country;

}
