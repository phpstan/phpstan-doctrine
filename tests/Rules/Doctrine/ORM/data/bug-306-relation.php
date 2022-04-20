<?php // lint >= 8.0

namespace PHPStan\Rules\Doctrine\ORM\Bug306Relation;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class MyBrokenEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="int")
	 * @var int|null
	 */
	private $id;

	public function __construct(
		/**
		 * @ORM\OneToMany(targetEntity="PHPStan\Rules\Doctrine\ORM\AnotherEntity", mappedBy="manyToOne")
		 */
		private \Doctrine\Common\Collections\Collection $genericCollection
	)
	{
	}
}
