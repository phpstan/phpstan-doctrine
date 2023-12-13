<?php declare(strict_types = 1);

namespace PHPStan\Platform\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="test_related")
 * @ORM\Entity
 */
#[ORM\Table(name: 'test_related')]
#[ORM\Entity]
class PlatformRelatedEntity
{

	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer", nullable=false)
	 * @var int
	 */
	#[ORM\Id]
	#[ORM\Column(type: 'integer', nullable: false)]
	public $id;

}
