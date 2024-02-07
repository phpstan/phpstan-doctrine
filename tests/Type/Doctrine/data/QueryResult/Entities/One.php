<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded as ORMEmbedded;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;

/**
 * @Entity
 */
#[Entity]
class One
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
	#[Column(type: 'bigint')]
	#[Id]
	public $id;

	/**
	 * @Column(type="integer")
	 *
	 * @var int
	 */
	#[Column(type: 'integer')]
	public $intColumn;

	/**
	 * @Column(type="string")
	 *
	 * @var string
	 */
	#[Column(type: 'string')]
	public $stringColumn;

	/**
	 * @Column(type="string", nullable=true)
	 *
	 * @var string|null
	 */
	#[Column(type: 'string', nullable: true)]
	public $stringNullColumn;

	/**
	 * @OneToOne(targetEntity="QueryResult\Entities\SubOne", cascade={"persist"})
	 * @JoinColumn(nullable=false)
	 *
	 * @var SubOne
	 */
	#[OneToOne(targetEntity: SubOne::class, cascade: ['persist'])]
	#[JoinColumn(nullable: false)]
	public $subOne;

	/**
	 * @OneToMany(targetEntity="QueryResult\Entities\Many", mappedBy="one")
	 *
	 * @var Collection<int,Many>
	 */
	#[OneToMany(targetEntity: Many::class, mappedBy: 'one')]
	public $manies;

	/**
	 * @ORMEmbedded(class="QueryResult\Entities\Embedded")
	 *
	 * @var Embedded
	 */
	#[ORMEmbedded(class: Embedded::class)]
	public $embedded;

	public function __construct()
	{
		$this->subOne = new SubOne();
	}
}
