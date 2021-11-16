<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @Entity
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="discr", type="string")
 * @DiscriminatorMap({
 *  "child"="QueryResult\Entities\JoinedChild"
 * })
 */
abstract class JoinedParent
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * @Column(type="integer")
	 *
	 * @var int
	 */
	public $parentColumn;

	/**
	 * @Column(type="integer", nullable=true)
	 *
	 * @var int
	 */
	public $parentNullColumn;
}
