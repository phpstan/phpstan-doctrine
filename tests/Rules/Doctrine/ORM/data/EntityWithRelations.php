<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="int")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	private $oneToOne;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	private $manyToOne;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	private $oneToMany;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	private $manyToMany;

}
