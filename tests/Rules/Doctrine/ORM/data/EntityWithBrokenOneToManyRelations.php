<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithBrokenOneToManyRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="one")
	 * @var iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	private $oneToManyWithIterableAnnotation;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="two")
	 * @var \Doctrine\Common\Collections\Collection
	 */
	private $oneToManyWithCollectionAnnotation;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="three")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity[]
	 */
	private $oneToManyWithArrayAnnotation;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="four")
	 * @var \Doctrine\Common\Collections\Collection&iterable<\PHPStan\Rules\Doctrine\ORM\AnotherEntity>
	 */
	private $oneToManyWithCorrectAnnotation;

	/**
	 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="five")
	 * @var \Doctrine\Common\Collections\Collection|\PHPStan\Rules\Doctrine\ORM\AnotherEntity[]
	 */
	private $oneToManyWithCorrectOldStyleAnnotation;

}
