<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MyBrokenEntity
{
	/**
	 * @ORM\Column(type="date_point")
	 * @var \DateTime
	 */
	private $invalidDatePoint;

	/**
	 * @ORM\Column(type="date_point")
	 * @var \Symfony\Component\Clock\DatePoint
	 */
	private $validDatePoint;

}
