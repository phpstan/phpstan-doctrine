<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MyBrokenEntity extends MyBrokenSuperclass
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="bigint")
	 * @var int|null
	 */
	private $id;

	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var string
	 */
	private $one;

	/**
	 * @ORM\Column(type="string")
	 * @var string|null
	 */
	private $two;

	/**
	 * @ORM\Column(type="datetime")
	 * @var \DateTimeImmutable
	 */
	private $three;

	/**
	 * @ORM\Column(type="datetime_immutable")
	 * @var \DateTime
	 */
	private $four;

	/**
	 * @ORM\Column(type="date")
	 * @var \DateTimeImmutable
	 */
	private $six;

}
