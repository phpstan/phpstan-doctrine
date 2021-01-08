<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\DBAL\QueryBuilder;

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class QueryBuilderExecuteMethodExtension implements DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return QueryBuilder::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'execute';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$defaultReturnType = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();

		$queryBuilderType = new ObjectType(QueryBuilder::class);
		$var = $methodCall->var;
		while ($var instanceof MethodCall) {
			$varType = $scope->getType($var->var);
			if (!$queryBuilderType->isSuperTypeOf($varType)->yes()) {
				return $defaultReturnType;
			}

			$nameObject = $var->name;
			if (!($nameObject instanceof Identifier)) {
				return $defaultReturnType;
			}

			$name = $nameObject->toString();
			if ($name === 'select' || $name === 'addSelect') {
				return new ObjectType(ResultStatement::class);
			}

			$var = $var->var;
		}

		return $defaultReturnType;
	}

}
