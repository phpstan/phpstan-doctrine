<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded as ORMEmbedded;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @Entity
 */
class One
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
	public $intColumn;

	/**
	 * @Column(type="string")
	 *
	 * @var string
	 */
	public $stringColumn;

	/**
	 * @Column(type="string", nullable=true)
	 *
	 * @var string|null
	 */
	public $stringNullColumn;

	/**
	 * @OneToMany(targetEntity="QueryResult\Entities\Many", mappedBy="one")
	 *
	 * @var Collection<int,Many>
	 */
	public $manies;

	/**
	 * @ORMEmbedded(class="QueryResult\Entities\Embedded")
	 *
	 * @var Embedded
	 */
	public $embedded;
}
