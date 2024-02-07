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
#[Entity]
class EntityWithEnum
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
	 * @Column(type="string", enumType="QueryResult\EntitiesEnum\StringEnum")
	 */
	#[Column(type: 'string', enumType: StringEnum::class)]
	public $stringEnumColumn;

	/**
	 * @Column(type="integer", enumType="QueryResult\EntitiesEnum\IntEnum")
	 */
	#[Column(type: 'integer', enumType: IntEnum::class)]
	public $intEnumColumn;

	/**
	 * @Column(type="string", enumType="QueryResult\EntitiesEnum\IntEnum")
	 */
	#[Column(type: 'string', enumType: IntEnum::class)]
	public $intEnumOnStringColumn;
}
