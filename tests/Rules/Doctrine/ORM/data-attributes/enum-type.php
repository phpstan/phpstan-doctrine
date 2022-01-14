<?php // lint >= 8.1

namespace PHPStan\Rules\Doctrine\ORMAttributes;

use Doctrine\ORM\Mapping as ORM;

enum FooEnum: string {

	case ONE = 'one';
	case TWO = 'two';

}

enum BarEnum: string {

	case ONE = 'one';
	case TWO = 'two';

}

#[ORM\Entity]
class Foo
{


	#[ORM\Column(nullable: false)]
	#[ORM\Id]
	#[ORM\GeneratedValue]
	private ?int $id = null;

	#[ORM\Column(type: "string", enumType: FooEnum::class)]
	public FooEnum $type1;

	#[ORM\Column(type: "string", enumType: FooEnum::class)]
	public BarEnum $type2;

}
