<?php // lint >= 7.4

namespace MissingReadOnlyPropertyAssignPhpDoc;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Entity
{

	/**
	 * @ORM\Id
	 * @ORM\GeneratedValue
	 * @ORM\Column
	 * @readonly
	 */
	private int $id; // ok because of PropertiesExtension

}
