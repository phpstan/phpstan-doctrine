<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class AnotherEntity
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
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithRelations")
	 */
	#[ORM\ManyToOne(targetEntity: EntityWithRelations::class)]
	private $manyToOne;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	#[ORM\ManyToOne(targetEntity: EntityWithBrokenOneToManyRelations::class)]
	private $one;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	#[ORM\ManyToOne(targetEntity: EntityWithBrokenOneToManyRelations::class)]
	private $two;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	#[ORM\ManyToOne(targetEntity: EntityWithBrokenOneToManyRelations::class)]
	private $three;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	#[ORM\ManyToOne(targetEntity: EntityWithBrokenOneToManyRelations::class)]
	private $four;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	#[ORM\ManyToOne(targetEntity: EntityWithBrokenOneToManyRelations::class)]
	private $five;

}
