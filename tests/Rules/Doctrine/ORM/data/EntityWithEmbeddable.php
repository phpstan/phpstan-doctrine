<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithEmbeddable
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Embedded(class=Embeddable::class)
	 * @var Embeddable
	 */
	private $embedded;

	/**
	 * @ORM\Embedded(class=Embeddable::class)
	 * @var ?Embeddable
	 */
	private $nullable;
}
