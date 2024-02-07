<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\GeneratedValue;

/**
 * @Entity
 */
#[Entity]
class SubOne
{
	/**
	 * @GeneratedValue()
	 * @Column(type="integer")
	 * @Id
	 *
	 * @var string
	 */
	#[GeneratedValue]
	#[Column(type: 'integer')]
	#[Id]
	public $id;
}
