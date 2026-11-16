<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass()
 */
abstract class GenericRepositorySuperclass
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 * @var int
	 */
	private $id;

	/**
	 * @ORM\Column(type="string")
	 * @var string
	 */
	private $shared;

}
