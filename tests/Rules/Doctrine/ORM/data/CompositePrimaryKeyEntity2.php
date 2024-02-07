<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class CompositePrimaryKeyEntity2
{

	/**
	 * @ORM\Id()
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithRelations", inversedBy="countries")
	 * @var EntityWithRelations
	 */
	#[ORM\Id]
	#[ORM\ManyToOne(targetEntity: EntityWithRelations::class, inversedBy: 'countries')]
	private $post;

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="string")
	 * @var string
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'string')]
	private $country;

}
