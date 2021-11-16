<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * @Entity
 */
class JoinedChild extends JoinedParent
{
	/**
	 * @Column(type="integer")
	 *
	 * @var int
	 */
	public $childColumn;

	/**
	 * @Column(type="integer", nullable=true)
	 *
	 * @var int
	 */
	public $childNullColumn;
}
