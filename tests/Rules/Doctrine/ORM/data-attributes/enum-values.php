<?php

namespace PHPStan\Rules\Doctrine\ORMAttributes;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FooValues
{
	/** @var string */
	#[ORM\Column(type: "string", options: ['values' => ['a', 'b', 'c']])]
	public $type1;

	/** @var 'a'|'b'|'c' */
	#[ORM\Column(type: "string", options: ['values' => ['a', 'b', 'c']])]
	public $type2;

	/** @var 'a'|'b' */
	#[ORM\Column(type: "string", options: ['values' => ['a', 'b', 'c']])]
	public $type3;
}
