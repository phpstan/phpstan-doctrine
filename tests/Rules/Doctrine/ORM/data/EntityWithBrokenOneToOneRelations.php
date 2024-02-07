<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class EntityWithBrokenOneToOneRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	#[ORM\Id]
	#[ORm\Column(type: 'integer')]
	private $id;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	#[ORM\OneToOne(targetEntity: AnotherEntity::class)]
	private $oneToOneNullableBoth;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	#[ORM\OneToOne(targetEntity: AnotherEntity::class)]
	#[ORM\JoinColumn(nullable: false)]
	private $oneToOneNullableProperty;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	#[ORM\OneToOne(targetEntity: AnotherEntity::class)]
	private $oneToOneNullableColumn;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	#[ORM\OneToOne(targetEntity: AnotherEntity::class)]
	#[ORM\JoinColumn(nullable: false)]
	private $oneToOneNonNullable;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\MyEntity|null
	 */
	#[ORM\OneToOne(targetEntity: AnotherEntity::class)]
	private $oneToOneWrongClass;

}
