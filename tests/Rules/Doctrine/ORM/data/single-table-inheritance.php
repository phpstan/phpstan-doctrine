<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM\SingleTableInheritance;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *  "base"="PHPStan\Rules\Doctrine\ORM\SingleTableInheritance\BaseEntity",
 *  "child"="PHPStan\Rules\Doctrine\ORM\SingleTableInheritance\ChildEntity"
 * })
 */
class BaseEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Column(type="string")
	 *
	 * @var string
	 */
	private $baseColumn;

}

/**
 * @ORM\Entity()
 */
class ChildEntity extends BaseEntity
{

	/**
	 * The column must be nullable in the database because it is shared with the rest of the
	 * single table inheritance hierarchy, but the property itself is always set.
	 *
	 * @ORM\Column(type="string", nullable=true)
	 *
	 * @var string
	 */
	private $childColumn;

	/**
	 * Nullable property is fine too.
	 *
	 * @ORM\Column(type="string", nullable=true)
	 *
	 * @var string|null
	 */
	private $childNullableColumn;

	/**
	 * Genuine type mismatches in child entities are still reported.
	 *
	 * @ORM\Column(type="string", nullable=true)
	 *
	 * @var int
	 */
	private $childBrokenColumn;

}
