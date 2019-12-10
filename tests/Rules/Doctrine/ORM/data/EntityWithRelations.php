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
	 * @ORM\Column(type="integer")
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

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 */
	private $mixed;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var int&string
	 */
	private $never;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection<AnotherEntity>
	 */
	private $genericCollection;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection<mixed, AnotherEntity>
	 */
	private $genericCollection2;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection<int, AnotherEntity>
	 */
	private $genericCollection3;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection<int, AnotherEntity>
	 */
	private $brokenCollectionAnnotation;

}
