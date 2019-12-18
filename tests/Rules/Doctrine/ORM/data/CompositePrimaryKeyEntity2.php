<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class CompositePrimaryKeyEntity2
{

	/**
	 * @ORM\Id()
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithRelations", inversedBy="countries")
	 * @var EntityWithRelations
	 */
	private $post;

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="string")
	 * @var string
	 */
	private $country;

}
