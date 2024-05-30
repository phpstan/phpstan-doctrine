<?php declare(strict_types = 1);

namespace PHPStan\Platform\MatrixEntity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="test")
 * @ORM\Entity
 */
class TestEntity
{

	/**
	 * @ORM\Id
	 * @ORM\Column(type="string", name="col_string", nullable=false)
	 * @var string
	 */
	public $col_string;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="boolean", name="col_bool", nullable=false)
	 * @var bool
	 */
	public $col_bool;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="float", name="col_float", nullable=false)
	 * @var float
	 */
	public $col_float;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="decimal", name="col_decimal", nullable=false, scale=1, precision=2)
	 * @var string
	 */
	public $col_decimal;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer", name="col_int", nullable=false)
	 * @var int
	 */
	public $col_int;

	/**
	 * @ORM\Id
	 * @ORM\Column(type="bigint", name="col_bigint", nullable=false)
	 * @var int
	 */
	public $col_bigint;

}
