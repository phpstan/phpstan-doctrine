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

	private bool $enabled;

	public function __construct(bool $enabled)
	{
		$this->enabled = $enabled;
	}

	public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
	{
		if (!$typeNode instanceof IdentifierTypeNode) {
			return null;
		}

		if ($typeNode->name !== '__doctrine-literal-string') {
			return null;
		}

		if ($this->enabled) {
			return new IntersectionType([
				new StringType(),
				new AccessoryLiteralStringType(),
			]);
		}

		return new StringType();
	}

}
