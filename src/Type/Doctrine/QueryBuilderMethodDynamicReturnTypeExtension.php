<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class QueryBuilderMethodDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string|null */
	private $queryBuilderClass;

	public function __construct(?string $queryBuilderClass)
	{
		$this->queryBuilderClass = $queryBuilderClass;
	}

	public function getClass(): string
	{
		return $this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder';
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
		$returnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->args,
			$methodReflection->getVariants()
		)->getReturnType();
		if (!(new ObjectType($this->getClass()))->isSuperTypeOf($returnType)->yes()) {
			return $returnType;
		}

		$calledOnType = $scope->getType($methodCall->var);
		if (
			!$calledOnType instanceof QueryBuilderType
			|| !$methodCall->name instanceof Identifier
		) {
			return $returnType;
		}

		return $calledOnType->append($methodCall);
	}

}
