<?php declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{

	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column(options: ['unsigned' => true])]
	private ?int $id = null;

}
