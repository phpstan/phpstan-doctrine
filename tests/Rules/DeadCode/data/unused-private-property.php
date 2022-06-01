<?php // lint >= 7.4

namespace UnusedPrivateProperty;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class EntityWithAGeneratedId
{

	/**
	 * @ORM\Id
	 * @ORM\GeneratedValue
	 * @ORM\Column
	 */
	private int $id; // ok, ID is generated

	/**
	 * @ORM\Column
	 */
	private int $unused;

	private int $unused2;

}

/**
 * @ORM\Entity(readOnly=true)
 */
class ReadOnlyEntity
{

	/**
	 * @ORM\Id
	 * @ORM\Column
	 */
	private int $id; // ok, entity is read only

}

/**
 * @ORM\Entity(readOnly=true)
 */
class ReadOnlyEntityWithConstructor
{

	/**
	 * @ORM\Id
	 * @ORM\Column
	 */
	private int $id;

	public function __construct()
	{
	}

}
