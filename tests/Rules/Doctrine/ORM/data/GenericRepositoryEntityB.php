<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class GenericRepositoryEntityB extends GenericRepositorySuperclass
{

	/**
	 * @ORM\Column(type="string")
	 * @var string
	 */
	private $onlyB;

}
