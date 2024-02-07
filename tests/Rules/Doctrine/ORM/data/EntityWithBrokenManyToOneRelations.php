<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class EntityWithBrokenManyToOneRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	#[ORM\Id]
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $manyToOneNullableBoth;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $manyToOneNullableProperty;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $manyToOneNullableColumn;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $manyToOneNonNullable;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\MyEntity|null
	 */
	#[ORM\ManyToOne(targetEntity: AnotherEntity::class)]
	private $manyToOneWrongClass;

}
