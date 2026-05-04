<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\EntityRepository;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;

class EntityRepositoryMatchingDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return EntityRepository::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'matching';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$callerType = $scope->getType($methodCall->var);
		$entityType = $callerType->getTemplateType(EntityRepository::class, 'TEntityClass');

		return new IntersectionType([
			new GenericObjectType('Doctrine\Common\Collections\Collection', [new IntegerType(), $entityType]),
			new GenericObjectType('Doctrine\Common\Collections\Selectable', [new IntegerType(), $entityType]),
		]);
	}

}
