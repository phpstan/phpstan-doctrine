<?php // lint >= 7.4

namespace MissingReadOnlyPropertyAssignPhpDoc;

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
	 * @readonly
	 */
	private int $id; // ok, ID is generated

	/**
	 * @ORM\Column
	 * @readonly
	 */
	private int $unassigned;

	/**
	 * @ORM\Column
	 * @readonly
	 */
	private int $doubleAssigned;

	public function __construct(int $doubleAssigned)
	{
		$this->doubleAssigned = $doubleAssigned;
		$this->doubleAssigned = 17;
	}

}

/**
 * @ORM\Entity(readOnly=true)
 */
class ReadOnlyEntity
{

	/**
	 * @ORM\Id
	 * @ORM\Column
	 * @readonly
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
	 * @readonly
	 */
	private int $id;

	public function __construct()
	{
	}

}
