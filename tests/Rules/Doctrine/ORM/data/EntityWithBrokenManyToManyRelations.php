<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithBrokenManyToManyRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	private $manyToManyWithIterableAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection
	 */
	private $manyToManyWithCollectionAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity[]
	 */
	private $manyToManyWithArrayAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	private $manyToManyWithCorrectAnnotation;

	/**
	 * @ORM\ManyToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \Doctrine\Common\Collections\Collection|\PHPStan\Rules\Doctrine\ORM\AnotherEntity[]
	 */
	private $manyToManyWithCorrectOldStyleAnnotation;

}
