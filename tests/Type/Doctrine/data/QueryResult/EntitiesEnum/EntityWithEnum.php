<?php declare(strict_types=1);

namespace QueryResult\EntitiesEnum;

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
class EntityWithEnum
{
	/**
	 * @Column(type="bigint")
	 * @Id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * @Column(type="string", enumType="QueryResult\EntitiesEnum\StringEnum")
	 */
	public $stringEnumColumn;

	/**
	 * @Column(type="integer", enumType="QueryResult\EntitiesEnum\IntEnum")
	 */
	public $intEnumColumn;

	/**
	 * @Column(type="string", enumType="QueryResult\EntitiesEnum\IntEnum")
	 */
	public $intEnumOnStringColumn;
}
