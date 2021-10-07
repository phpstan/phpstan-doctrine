<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\CustomObjectManager;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class MyEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;

	public function doSomethingElse(): void
	{
	}

}
