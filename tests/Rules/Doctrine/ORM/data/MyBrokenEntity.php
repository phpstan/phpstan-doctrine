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

	/**
	 * @ORM\Column(type="date")
	 */
	private $mixed;

	/**
	 * @ORM\Column(type="date")
	 * @var int&string
	 */
	private $never;

	/**
	 * @ORM\Column(type="uuid")
	 * @var \Ramsey\Uuid\UuidInterface
	 */
	private $uuid;

	/**
	 * @ORM\Column(type="uuid")
	 * @var int
	 */
	private $uuidInvalidType;

	/**
	 * @ORM\Column(type="array")
	 * @var int[]
	 */
	private $arrayOfIntegers;

	/**
	 * @ORM\Column(type="array")
	 * @var mixed[][]
	 */
	private $arrayOfArrays;

	/**
	 * @ORM\Column(type="array")
	 * @var mixed[]
	 */
	private $mixeds;

	/**
	 * @ORM\Column(type="array")
	 * @var array|null
	 */
	private $arrayOrNull;

	/**
	 * @ORM\Column(type="array")
	 * @var int[]|null
	 */
	private $arrayOfIntegersOrNull;

	/**
	 * @ORM\Column(type="decimal")
	 * @var int|float|numeric-string
	 */
	private $decimal;

	/**
	 * @ORM\Column(type="decimal")
	 * @var int|float|string
	 */
	private $decimalWithString;

	/**
	 * @ORM\Column(type="decimal")
	 * @var string
	 */
	private $decimalWithString2;

	/**
	 * @ORM\Column(type="string")
	 * @var numeric-string
	 */
	private $numericString;

	/**
	 * @ORM\Column(type="carbon")
	 * @var \Carbon\CarbonImmutable
	 */
	private $invalidCarbon;

	/**
	 * @ORM\Column(type="carbon_immutable")
	 * @var \Carbon\Carbon
	 */
	private $invalidCarbonImmutable;

	/**
	 * @ORM\Column(type="carbon")
	 * @var \Carbon\Carbon
	 */
	private $validCarbon;

	/**
	 * @ORM\Column(type="carbon_immutable")
	 * @var \Carbon\CarbonImmutable
	 */
	private $validCarbonImmutable;
}
