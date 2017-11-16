<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class EntityManagerFindDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return \Doctrine\ORM\EntityManager::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), [
			'find',
			'getReference',
			'getPartialReference',
		], true);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$mixedType = new MixedType();
		if (count($methodCall->args) === 0) {
			return $mixedType;
		}
		$arg = $methodCall->args[0]->value;
		if (!($arg instanceof \PhpParser\Node\Expr\ClassConstFetch)) {
			return $mixedType;
		}

		$class = $arg->class;
		if (!($class instanceof \PhpParser\Node\Name)) {
			return $mixedType;
		}

		$class = (string) $class;

		if ($class === 'static') {
			return $mixedType;
		}

		if ($class === 'self') {
			$class = $scope->getClassReflection()->getName();
		}

		$type = new ObjectType($class);
		if ($methodReflection->getName() === 'find') {
			$type = TypeCombinator::addNull($type);
		}

		return $type;
	}

}
