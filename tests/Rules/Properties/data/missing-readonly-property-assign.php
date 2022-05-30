<?php // lint >= 8.1

namespace MissingReadOnlyPropertyAssign;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Entity
{

	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private readonly int $id; // ok because of PropertiesExtension

}
