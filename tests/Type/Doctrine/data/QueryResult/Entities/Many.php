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
class Many
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
	 * @Column(type="datetime")
	 *
	 * @var \DateTime
	 */
	public $datetimeColumn;

	/**
	 * @Column(type="datetime_immutable")
	 *
	 * @var \DateTimeImmutable
	 */
	public $datetimeImmutableColumn;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\One", inversedBy="manies")
	 * @JoinColumn(nullable=false)
	 *
	 * @var One
	 */
	public $one;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\One")
	 * @JoinColumn(nullable=true)
	 *
	 * @var One|null
	 */
	public $oneNull;

	/**
	 * @ManyToOne(targetEntity="QueryResult\Entities\One")
	 *
	 * @var One|null
	 */
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
	public $compoundPkAssoc;

	/**
	 * @ORM\Column(type="simple_array")
	 * @var list<string>
	 */
	public $simpleArrayColumn;
}

/**
 * @ORM\Entity
 */
class Bug245Episode
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
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
	private $segments;
}

/**
 * @ORM\Entity
 */
class Bug245Segment
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
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
	private $episode;
}
