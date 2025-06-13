<?php declare(strict_types=1);

namespace QueryResult\EntitiesDbal42;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/**
 * @Entity
 */
class Dbal4Entity
{

	/**
	 * @Column(type="integer")
	 * @GeneratedValue(strategy="AUTO")
	 * @Id()
	 *
	 * @var int
	 */
	public $id;

	/**
	 * @Column(type="enum", options={"values"={"a", "b", "c"}})
	 * @var string
	 */
	public $enum;  // dbal 4.2+

	/**
	 * @Column(type="smallfloat")
	 * @var float
	 */
	public $smallfloat; // dbal 4.1+
}
