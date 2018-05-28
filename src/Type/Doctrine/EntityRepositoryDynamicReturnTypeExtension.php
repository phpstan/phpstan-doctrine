<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class EntityRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return 'Doctrine\ORM\EntityRepository';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		$methodName = $methodReflection->getName();
		return strpos($methodName, 'findBy') === 0
			|| strpos($methodName, 'findOneBy') === 0
			|| $methodName === 'findAll'
			|| $methodName === 'find';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		if (!$calledOnType instanceof EntityRepositoryType) {
			return new MixedType();
		}
		$methodName = $methodReflection->getName();
		$entityType = new ObjectType($calledOnType->getEntityClass());

		if ($methodName === 'find' || strpos($methodName, 'findOneBy') === 0) {
			return TypeCombinator::addNull($entityType);
		}

		return new ArrayType(new IntegerType(), $entityType);
	}

}
