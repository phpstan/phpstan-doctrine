<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
#[ORM\Entity]
class MyBrokenEntity extends MyBrokenSuperclass
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="bigint")
	 * @var int|null
	 */
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(type: 'bigint')]
	private $id;

	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @var string
	 */
	#[ORM\Column(type: 'string', nullable: true)]
	private $one;

	/**
	 * @ORM\Column(type="string")
	 * @var string|null
	 */
	#[ORM\Column(type: 'string')]
	private $two;

	/**
	 * @ORM\Column(type="datetime")
	 * @var \DateTimeImmutable
	 */
	#[ORM\Column(type: 'datetime')]
	private $three;

	/**
	 * @ORM\Column(type="datetime_immutable")
	 * @var \DateTime
	 */
	#[ORM\Column(type: 'date_immutable')]
	private $four;

	/**
	 * @ORM\Column(type="date")
	 * @var \DateTimeImmutable
	 */
	#[ORM\Column(type: 'date')]
	private $six;

	/**
	 * @ORM\Column(type="date")
	 */
	#[ORM\Column(type: 'date')]
	private $mixed;

	/**
	 * @ORM\Column(type="date")
	 * @var int&string
	 */
	#[ORM\Column(type: 'date')]
	private $never;

	/**
	 * @ORM\Column(type="uuid")
	 * @var \Ramsey\Uuid\UuidInterface
	 */
	#[ORM\Column(type: 'uuid')]
	private $uuid;

	/**
	 * @ORM\Column(type="uuid")
	 * @var int
	 */
	#[ORM\Column(type: 'uuid')]
	private $uuidInvalidType;

	/**
	 * @ORM\Column(type="array")
	 * @var int[]
	 */
	#[ORM\Column(type: 'array')]
	private $arrayOfIntegers;

	/**
	 * @ORM\Column(type="array")
	 * @var mixed[][]
	 */
	#[ORM\Column(type: 'array')]
	private $arrayOfArrays;

	/**
	 * @ORM\Column(type="array")
	 * @var mixed[]
	 */
	#[ORM\Column(type: 'array')]
	private $mixeds;

	/**
	 * @ORM\Column(type="array")
	 * @var array|null
	 */
	#[ORM\Column(type: 'array')]
	private $arrayOrNull;

	/**
	 * @ORM\Column(type="array")
	 * @var int[]|null
	 */
	#[ORM\Column(type: 'array')]
	private $arrayOfIntegersOrNull;

	/**
	 * @ORM\Column(type="decimal")
	 * @var int|float|numeric-string
	 */
	#[ORM\Column(type: 'decimal')]
	private $decimal;

	/**
	 * @ORM\Column(type="decimal")
	 * @var int|float|string
	 */
	#[ORM\Column(type: 'decimal')]
	private $decimalWithString;

	/**
	 * @ORM\Column(type="decimal")
	 * @var string
	 */
	#[ORM\Column(type: 'decimal')]
	private $decimalWithString2;

	/**
	 * @ORM\Column(type="string")
	 * @var numeric-string
	 */
	#[ORM\Column(type: 'string')]
	private $numericString;

	/**
	 * @ORM\Column(type="carbon")
	 * @var \Carbon\CarbonImmutable
	 */
	#[ORM\Column(type: 'carbon')]
	private $invalidCarbon;

	/**
	 * @ORM\Column(type="carbon_immutable")
	 * @var \Carbon\Carbon
	 */
	#[ORM\Column(type: 'carbon_immutable')]
	private $invalidCarbonImmutable;

	/**
	 * @ORM\Column(type="carbon")
	 * @var \Carbon\Carbon
	 */
	#[ORM\Column(type: 'carbon')]
	private $validCarbon;

	/**
	 * @ORM\Column(type="carbon_immutable")
	 * @var \Carbon\CarbonImmutable
	 */
	#[ORM\Column(type: 'carbon_immutable')]
	private $validCarbonImmutable;

	/**
	 * @ORM\Column(type="json")
	 * @var EmptyObject
	 */
	#[ORM\Column(type: 'json')]
	private $incompatibleJsonValueObject;

	/**
	 * @ORM\Column(type="simple_array")
	 * @var int[]
	 */
	#[ORM\Column(type: 'simple_array')]
	private $invalidSimpleArray;

	/**
	 * @ORM\Column(type="simple_array")
	 * @var list<string>
	 */
	#[ORM\Column(type: 'simple_array')]
	private $validSimpleArray;
}
