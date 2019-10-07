<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class AnotherEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithRelations")
	 */
	private $manyToOne;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	private $one;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	private $two;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	private $three;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	private $four;

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\EntityWithBrokenOneToManyRelations")
	 */
	private $five;

}
