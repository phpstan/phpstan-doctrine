<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class EntityWithBrokenManyToManyRelations
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
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	#[ORM\ManyToMany(targetEntity: AnotherEntity::class)]
	private $manyToManyWithIterableAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection
	 */
	#[ORM\ManyToMany(targetEntity: AnotherEntity::class)]
	private $manyToManyWithCollectionAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity[]
	 */
	#[ORM\ManyToMany(targetEntity: AnotherEntity::class)]
	private $manyToManyWithArrayAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	#[ORM\ManyToMany(targetEntity: AnotherEntity::class)]
	private $manyToManyWithCorrectAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection|\PHPStan\Rules\Doctrine\ORM\AnotherEntity[]
	 */
	#[ORM\ManyToMany(targetEntity: AnotherEntity::class)]
	private $manyToManyWithCorrectOldStyleAnnotation;

}
