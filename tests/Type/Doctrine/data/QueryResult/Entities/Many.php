<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\JoinColumns;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Entity
 */
#[Entity]
class Many
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
	 * @Column(type="datetime")
	 *
	 * @var \DateTime
	 */
	#[Column(type: 'datetime')]
	public $datetimeColumn;

	/**
	 * @Column(type="datetime_immutable")
	 *
	 * @var \DateTimeImmutable
	 */
	#[Column(type: 'datetime_immutable')]
	public $datetimeImmutableColumn;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\One", inversedBy="manies")
	 * @JoinColumn(nullable=false)
	 *
	 * @var One
	 */
	#[ManyToOne(targetEntity: One::class, inversedBy: 'manies')]
	public $one;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\One")
	 * @JoinColumn(nullable=true)
	 *
	 * @var One|null
	 */
	#[ManyToOne(targetEntity: One::class)]
	public $oneNull;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\One")
	 *
	 * @var One|null
	 */
	#[ManyToOne(targetEntity: One::class)]
	public $oneDefaultNullability;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\CompoundPk")
	 * @JoinColumns({
	 *  @JoinColumn(name="compoundPk_id", referencedColumnName="id"),
	 *  @JoinColumn(name="compoundPk_version", referencedColumnName="version")
	 * })
	 *
	 * @var CompoundPk|null
	 */
	#[ManyToOne(targetEntity: CompoundPk::class)]
	#[JoinColumns([
		new JoinColumn(name: 'compoundPk_id', referencedColumnName: 'id'),
		new JoinColumn(name: 'compoundPk_version', referencedColumnName: 'version'),
	])]
	public $compoundPk;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\CompoundPkAssoc")
	 * @JoinColumns({
	 *  @JoinColumn(name="compoundPk_one", referencedColumnName="one_id"),
	 *  @JoinColumn(name="compoundPk_version", referencedColumnName="version")
	 * })
	 *
	 * @var CompoundPkAssoc|null
	 */
	#[ManyToOne(targetEntity: CompoundPkAssoc::class)]
	#[JoinColumns([
		new JoinColumn(name: 'compoundPk_one', referencedColumnName: 'one_id'),
		new JoinColumn(name: 'compoundPk_version', referencedColumnName: 'version'),
	])]
	public $compoundPkAssoc;

	/**
	 * @ORM\Column(type="simple_array")
	 * @var list<string>
	 */
	#[Column(type: 'simple_array')]
	public $simpleArrayColumn;
}

/**
 * @ORM\Entity
 */
#[Entity]
class Bug245Episode
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
	#[Column(type: 'bigint')]
	public $id;

	/**
	 * @var \Doctrine\Common\Collections\Collection<Bug245Segment>
	 * @ORM\OneToMany(
	 *      targetEntity="\QueryResult\Entities\Bug245Segment",
	 *      mappedBy="episode",
	 *      cascade={"persist", "remove"},
	 *      orphanRemoval=true
	 * )
	 * @ORM\OrderBy({"position" = "ASC"})
	 */
	#[ORM\OneToMany(targetEntity: Bug245Segment::class, mappedBy: 'episode', cascade: ['persist', 'remove'], orphanRemoval: true)]
	#[ORM\OrderBy(['position' => 'ASC'])]
	private $segments;
}

/**
 * @ORM\Entity
 */
#[Entity]
class Bug245Segment
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
	#[Column(type: 'bigint')]
	public $id;

	/**
	 * @ORM\ManyToOne(
	 *     targetEntity="\QueryResult\Entities\Bug245Episode",
	 *     inversedBy="segments",
	 *     cascade={"persist"}
	 * )
	 * @ORM\JoinColumn(name="episode_id", referencedColumnName="id", nullable=false)
	 * @var Bug245Episode
	 */
	#[ManyToOne(targetEntity: Bug245Episode::class, inversedBy: 'segments', cascase: ['persist'])]
	#[JoinColumn(name: 'episode_id', referencedColumnName: 'id', nullable: false)]
	private $episode;
}
