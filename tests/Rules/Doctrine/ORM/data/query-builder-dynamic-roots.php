<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="kind", type="string")
 * @ORM\DiscriminatorMap({"a"=DynamicRootA::class, "b"=DynamicRootB::class})
 */
abstract class DynamicRoot
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	public $id;

}

/** @ORM\Entity() */
class DynamicRootA extends DynamicRoot
{

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\MyEntity")
	 * @var MyEntity|null
	 */
	public $channel;

}

/** @ORM\Entity() */
class DynamicRootB extends DynamicRoot
{

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\MyEntity")
	 * @var MyEntity|null
	 */
	public $channel;

}

/**
 * @ORM\Entity()
 * @ORM\InheritanceType("JOINED")
 * @ORM\DiscriminatorColumn(name="kind", type="string")
 * @ORM\DiscriminatorMap({"a"=PartialRootA::class, "b"=PartialRootB::class})
 */
abstract class PartialRoot
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	public $id;

}

/** @ORM\Entity() */
class PartialRootA extends PartialRoot
{

	/**
	 * @ORM\ManyToOne(targetEntity="PHPStan\Rules\Doctrine\ORM\MyEntity")
	 * @var MyEntity|null
	 */
	public $channel;

}

/** @ORM\Entity() */
class PartialRootB extends PartialRoot
{

}
