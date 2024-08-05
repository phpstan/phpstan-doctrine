<?php declare(strict_types = 1);

namespace PHPStan\PhpDoc\Doctrine;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Accessory\AccessoryLiteralStringType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

class DoctrineLiteralStringTypeNodeResolverExtension implements TypeNodeResolverExtension
{

	/** @var bool */
	private $bleedingEdge;

	public function __construct(bool $bleedingEdge)
	{
		$this->bleedingEdge = $bleedingEdge;
	}

	public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
	{
		if (!$typeNode instanceof IdentifierTypeNode) {
			return null;
		}

		if ($typeNode->name !== '__doctrine-literal-string') {
			return null;
		}

		if ($this->bleedingEdge) {
			return new IntersectionType([
				new StringType(),
				new AccessoryLiteralStringType(),
			]);
		}

		return new StringType();
	}

}
