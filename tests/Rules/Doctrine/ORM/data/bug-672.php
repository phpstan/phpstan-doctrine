<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM\Bug672;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class SmallIntEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Column(type="smallint")
	 * @var int<-32768, 32767>
	 */
	private $signed;

	/**
	 * @ORM\Column(type="smallint", options={"unsigned": false})
	 * @var int<-32768, 32767>
	 */
	private $explicitlySigned;

	/**
	 * @ORM\Column(type="smallint", options={"unsigned": true})
	 * @var int<0, 65535>
	 */
	private $unsigned;

	/**
	 * @ORM\Column(type="smallint")
	 * @var int
	 */
	private $int;

	/**
	 * @ORM\Column(type="smallint", nullable=true)
	 * @var int<-32768, 32767>|null
	 */
	private $nullable;

	/**
	 * @ORM\Column(type="smallint")
	 * @var int<0, 65535>
	 */
	private $signedAsUnsigned;

	/**
	 * @ORM\Column(type="smallint", options={"unsigned": true})
	 * @var int<-32768, 32767>
	 */
	private $unsignedAsSigned;

}
