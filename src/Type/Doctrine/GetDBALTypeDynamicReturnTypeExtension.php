<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

final class GetDBALTypeDynamicReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return \Doctrine\DBAL\Types\Type::class;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getType';
	}

	public function getTypeFromStaticMethodCall(
		MethodReflection $methodReflection,
		StaticCall $methodCall,
		Scope $scope
	): Type
	{
		if (count($methodCall->args) === 0) {
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}

		$dbalTypeArg = $methodCall->args[0]->value;
		return $this->detectReturnType($methodReflection, $dbalTypeArg);
	}

	private function detectReturnType(MethodReflection $methodReflection, Expr $dbalTypeArg): Type
	{
		if ($dbalTypeArg instanceof Expr\ClassConstFetch && $dbalTypeArg->class instanceof Name) {
			return new ObjectType($dbalTypeArg->class->toString());
		}

		if ($dbalTypeArg instanceof Scalar\String_) {
			return new ObjectType($dbalTypeArg->value);
		}

		return ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		)->getReturnType();
	}

}
