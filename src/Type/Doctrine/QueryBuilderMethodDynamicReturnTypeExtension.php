<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\MixedType;
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
		$returnType = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		)->getReturnType();
		if ($returnType instanceof MixedType) {
			return false;
		}
		return $returnType->isSuperTypeOf(new ObjectType($this->getClass()))->yes();
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		if (!$calledOnType instanceof QueryBuilderType) {
			return $calledOnType;
		}

		return $calledOnType->append($methodCall);
	}

}
