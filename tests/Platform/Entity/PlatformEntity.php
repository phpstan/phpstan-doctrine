<?php declare(strict_types = 1);

namespace PHPStan\Platform\Entity;

use DateTimeInterface;
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
	 * @ORM\ManyToOne(targetEntity=PlatformRelatedEntity::class)
	 * @ORM\JoinColumn(name="related_entity_id", referencedColumnName="id", nullable=false)
	 * @var PlatformRelatedEntity
	 */
	#[ORM\ManyToOne(targetEntity: PlatformRelatedEntity::class)]
	#[ORM\JoinColumn(name: 'related_entity_id', referencedColumnName: 'id', nullable: false)]
	public $related_entity;

	/**
	 * @ORM\Column(type="string", name="col_string", nullable=false)
	 * @var string
	 */
	#[ORM\Column(type: 'string', name: 'col_string', nullable: false)]
	public $col_string;

	/**
	 * @ORM\Column(type="string", name="col_string_nullable", nullable=true)
	 * @var string|null
	 */
	#[ORM\Column(type: 'string', name: 'col_string_nullable', nullable: true)]
	public $col_string_nullable;

	/**
	 * @ORM\Column(type="boolean", name="col_bool", nullable=false)
	 * @var bool
	 */
	#[ORM\Column(type: 'boolean', name: 'col_bool', nullable: false)]
	public $col_bool;

	/**
	 * @ORM\Column(type="boolean", name="col_bool_nullable", nullable=true)
	 * @var bool|null
	 */
	#[ORM\Column(type: 'boolean', name: 'col_bool_nullable', nullable: true)]
	public $col_bool_nullable;

	/**
	 * @ORM\Column(type="float", name="col_float", nullable=false)
	 * @var float
	 */
	#[ORM\Column(type: 'float', name: 'col_float', nullable: false)]
	public $col_float;

	/**
	 * @ORM\Column(type="float", name="col_float_nullable", nullable=true)
	 * @var float|null
	 */
	#[ORM\Column(type: 'float', name: 'col_float_nullable', nullable: true)]
	public $col_float_nullable;

	/**
	 * @ORM\Column(type="decimal", name="col_decimal", nullable=false, scale=1, precision=2)
	 * @var string
	 */
	#[ORM\Column(type: 'decimal', name: 'col_decimal', nullable: false, scale: 1, precision: 2)]
	public $col_decimal;

	/**
	 * @ORM\Column(type="decimal", name="col_decimal_nullable", nullable=true, scale=1, precision=2)
	 * @var string|null
	 */
	#[ORM\Column(type: 'decimal', name: 'col_decimal_nullable', nullable: true, scale: 1, precision: 2)]
	public $col_decimal_nullable;

	/**
	 * @ORM\Column(type="integer", name="col_int", nullable=false)
	 * @var int
	 */
	#[ORM\Column(type: 'integer', name: 'col_int', nullable: false)]
	public $col_int;

	/**
	 * @ORM\Column(type="integer", name="col_int_nullable", nullable=true)
	 * @var int|null
	 */
	#[ORM\Column(type: 'integer', name: 'col_int_nullable', nullable: true)]
	public $col_int_nullable;

	/**
	 * @ORM\Column(type="bigint", name="col_bigint", nullable=false)
	 * @var int|string
	 */
	#[ORM\Column(type: 'bigint', name: 'col_bigint', nullable: false)]
	public $col_bigint;

	/**
	 * @ORM\Column(type="bigint", name="col_bigint_nullable", nullable=true)
	 * @var int|string|null
	 */
	#[ORM\Column(type: 'bigint', name: 'col_bigint_nullable', nullable: true)]
	public $col_bigint_nullable;

	/**
	 * @ORM\Column(type="mixed", name="col_mixed", nullable=false)
	 * @var mixed
	 */
	#[ORM\Column(type: 'mixed', name: 'col_mixed', nullable: false)]
	public $col_mixed;

	/**
	 * @ORM\Column(type="datetime", name="col_datetime", nullable=false)
	 * @var DateTimeInterface
	 */
	#[ORM\Column(type: 'datetime', name: 'col_datetime', nullable: false)]
	public $col_datetime;

}
