<?php declare(strict_types = 1);

namespace PHPStan\PhpDoc\Doctrine;

use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Doctrine\ObjectRepositoryType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;

class EntityRepositoryTypeNodeResolverExtension implements TypeNodeResolverExtension, TypeNodeResolverAwareExtension
{

	/** @var TypeNodeResolver */
	private $typeNodeResolver;

	public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
	{
		$this->typeNodeResolver = $typeNodeResolver;
	}

	public function getCacheKey(): string
	{
		return 'doctrine-v1';
	}

	public function resolve(TypeNode $typeNode, \PHPStan\Analyser\NameScope $nameScope): ?Type
	{
		if (!$typeNode instanceof GenericTypeNode) {
			return null;
		}

		if (count($typeNode->genericTypes) !== 1) {
			return null;
		}

		$repositoryType = $this->typeNodeResolver->resolve($typeNode->type, $nameScope);
		if (!$repositoryType instanceof TypeWithClassName) {
			return null;
		}
		if (!(new ObjectType('Doctrine\Common\Persistence\ObjectRepository'))->isSuperTypeOf($repositoryType)->yes()) {
			return null;
		}

		$entityType = $this->typeNodeResolver->resolve($typeNode->genericTypes[0], $nameScope);
		if (!$entityType instanceof TypeWithClassName) {
			return null;
		}

		return new ObjectRepositoryType($entityType->getClassName(), $repositoryType->getClassName());
	}

}
