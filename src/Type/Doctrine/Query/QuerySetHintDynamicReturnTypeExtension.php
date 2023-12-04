<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Doctrine\DoctrineTypeUtils;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;

class QuerySetHintDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return 'Doctrine\ORM\Query';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'setHint';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): ?Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		$queryTypes = DoctrineTypeUtils::getQueryTypes($calledOnType);
		if (count($queryTypes) === 0) {
			return null;
		}

		return TypeCombinator::union(...$queryTypes);
	}

}
