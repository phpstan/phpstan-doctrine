<?php declare(strict_types = 1);

namespace PHPStan\Platform\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="test")
 * @ORM\Entity
 */
#[ORM\Table(name: 'test')]
#[ORM\Entity]
class PlatformEntity
{

	/**
	 * @ORM\Id
	 * @ORM\Column(type="string",nullable=false)
	 * @var string
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'string', nullable: false)]
	public $id;

	/**
	 * @ORM\Column(type="string", name="col_string", nullable=false)
	 * @var string
	 */
	#[ORM\Column(type: 'string', name: 'col_string', nullable: false)]
	public $col_string;

	/**
	 * @ORM\Column(type="boolean", name="col_bool", nullable=false)
	 * @var bool
	 */
	#[ORM\Column(type: 'boolean', name: 'col_bool', nullable: false)]
	public $col_bool;

	/**
	 * @ORM\Column(type="float", name="col_float", nullable=false)
	 * @var float
	 */
	#[ORM\Column(type: 'float', name: 'col_float', nullable: false)]
	public $col_float;

	/**
	 * @ORM\Column(type="decimal", name="col_decimal", nullable=false, scale=1, precision=2)
	 * @var string
	 */
	#[ORM\Column(type: 'decimal', name: 'col_decimal', nullable: false, scale: 1, precision: 2)]
	public $col_decimal;

	/**
	 * @ORM\Column(type="integer", name="col_int", nullable=false)
	 * @var int
	 */
	#[ORM\Column(type: 'integer', name: 'col_int', nullable: false)]
	public $col_int;

	/**
	 * @ORM\Column(type="bigint", name="col_bigint", nullable=false)
	 * @var int|string
	 */
	#[ORM\Column(type: 'bigint', name: 'col_bigint', nullable: false)]
	public $col_bigint;

}
