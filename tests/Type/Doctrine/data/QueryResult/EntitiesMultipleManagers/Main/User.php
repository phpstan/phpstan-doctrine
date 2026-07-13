<?php declare(strict_types = 1);

namespace QueryResult\MultipleEntityManagers\Main;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/**
 * @Entity
 */
class User
{

	/**
	 * @Column(type="integer")
	 * @Id
	 *
	 * @var int
	 */
	public $id;

}
