<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class EntityWithRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	private $id;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	#[ORM\OneToOne(targetEntity: AnotherEntity::class)]
	private $oneToOne;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $manyToOne;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	#[ORM\OneToMany(targetEntity: AnotherEntity::class, mappedBy: 'manyToOne')]
	private $oneToMany;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	#[ORM\ManyToMany(targetEntity: AnotherEntity::class)]
	private $manyToMany;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $mixed;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var int&string
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $never;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection<AnotherEntity>
	 */
	#[ORM\OneToMany(targetEntity: AnotherEntity::class, mappedBy: 'manyToOne')]
	private $genericCollection;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection<mixed, AnotherEntity>
	 */
	#[ORM\OneToMany(targetEntity: AnotherEntity::class, mappedBy: 'manyToOne')]
	private $genericCollection2;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection<int, AnotherEntity>
	 */
	#[ORM\OneToMany(targetEntity: AnotherEntity::class, mappedBy: 'manyToOne')]
	private $genericCollection3;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection
	 */
	#[ORM\OneToMany(targetEntity: AnotherEntity::class, mappedBy: 'manyToOne')]
	private $genericCollection4;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
	 * @var \Doctrine\Common\Collections\Collection&iterable<MyEntity>
	 */
	#[ORM\OneToMany(targetEntity: AnotherEntity::class, mappedBy: 'manyToOne')]
	private $genericCollection5;

}
