<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithBrokenOneToOneRelations
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
	private $oneToOneNullableBoth;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	private $oneToOneNullableProperty;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	private $oneToOneNullableColumn;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	private $oneToOneNonNullable;

	/**
	 * @ORM\OneToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\MyEntity|null
	 */
	private $oneToOneWrongClass;

}
