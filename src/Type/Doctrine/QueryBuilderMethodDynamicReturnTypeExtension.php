<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Type;

class QueryBuilderMethodDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return 'Doctrine\ORM\QueryBuilder';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return true;
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		if (
			!$calledOnType instanceof QueryBuilderType
			|| !$methodCall->name instanceof Identifier
		) {
			return ParametersAcceptorSelector::selectFromArgs(
				$scope,
				$methodCall->args,
				$methodReflection->getVariants()
			)->getReturnType();
		}

		return $calledOnType->append($methodCall);
	}

}
