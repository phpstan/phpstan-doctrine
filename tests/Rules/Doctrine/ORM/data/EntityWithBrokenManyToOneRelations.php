<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class EntityWithBrokenManyToOneRelations
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="int")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	private $manyToOneNullableBoth;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity|null
	 */
	private $manyToOneNullableProperty;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	private $manyToOneNullableColumn;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @ORM\JoinColumn(nullable=false)
	 * @var \PHPStan\Rules\Doctrine\ORM\AnotherEntity
	 */
	private $manyToOneNonNullable;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity")
	 * @var \PHPStan\Rules\Doctrine\ORM\MyEntity|null
	 */
	private $manyToOneWrongClass;

}
