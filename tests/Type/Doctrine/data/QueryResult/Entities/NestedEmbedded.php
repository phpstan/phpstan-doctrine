<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @Embeddable
 */
class NestedEmbedded
{
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
}
