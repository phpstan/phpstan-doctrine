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
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'string', nullable: false)]
	public string $id;

	/**
	 * @ORM\ManyToOne(targetEntity=PlatformRelatedEntity::class)
	 * @ORM\JoinColumn(name="related_entity_id", referencedColumnName="id", nullable=false)
	 */
	#[ORM\ManyToOne(targetEntity: PlatformRelatedEntity::class)]
	#[ORM\JoinColumn(name: 'related_entity_id', referencedColumnName: 'id', nullable: false)]
	public PlatformRelatedEntity $related_entity;

	/** @ORM\Column(type="string", name="col_string", nullable=false) */
	#[ORM\Column(type: 'string', name: 'col_string', nullable: false)]
	public string $col_string;

	/** @ORM\Column(type="string", name="col_string_nullable", nullable=true) */
	#[ORM\Column(type: 'string', name: 'col_string_nullable', nullable: true)]
	public ?string $col_string_nullable = null;

	/** @ORM\Column(type="boolean", name="col_bool", nullable=false) */
	#[ORM\Column(type: 'boolean', name: 'col_bool', nullable: false)]
	public bool $col_bool;

	/** @ORM\Column(type="boolean", name="col_bool_nullable", nullable=true) */
	#[ORM\Column(type: 'boolean', name: 'col_bool_nullable', nullable: true)]
	public ?bool $col_bool_nullable = null;

	/** @ORM\Column(type="float", name="col_float", nullable=false) */
	#[ORM\Column(type: 'float', name: 'col_float', nullable: false)]
	public float $col_float;

	/** @ORM\Column(type="float", name="col_float_nullable", nullable=true) */
	#[ORM\Column(type: 'float', name: 'col_float_nullable', nullable: true)]
	public ?float $col_float_nullable = null;

	/** @ORM\Column(type="decimal", name="col_decimal", nullable=false, scale=1, precision=2) */
	#[ORM\Column(type: 'decimal', name: 'col_decimal', nullable: false, scale: 1, precision: 2)]
	public string $col_decimal;

	/** @ORM\Column(type="decimal", name="col_decimal_nullable", nullable=true, scale=1, precision=2) */
	#[ORM\Column(type: 'decimal', name: 'col_decimal_nullable', nullable: true, scale: 1, precision: 2)]
	public ?string $col_decimal_nullable = null;

	/** @ORM\Column(type="integer", name="col_int", nullable=false) */
	#[ORM\Column(type: 'integer', name: 'col_int', nullable: false)]
	public int $col_int;

	/** @ORM\Column(type="integer", name="col_int_nullable", nullable=true) */
	#[ORM\Column(type: 'integer', name: 'col_int_nullable', nullable: true)]
	public ?int $col_int_nullable = null;

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

	/** @ORM\Column(type="datetime", name="col_datetime", nullable=false) */
	#[ORM\Column(type: 'datetime', name: 'col_datetime', nullable: false)]
	public DateTimeInterface $col_datetime;

}
