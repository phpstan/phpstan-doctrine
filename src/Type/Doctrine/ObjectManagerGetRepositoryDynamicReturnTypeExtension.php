<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

class ObjectManagerGetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string */
	private $repositoryClass;

	public function __construct(string $repositoryClass)
	{
		$this->repositoryClass = $repositoryClass;
	}

	public function getClass(): string
	{
		return 'Doctrine\Common\Persistence\ObjectManager';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getRepository';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		if (count($methodCall->args) === 0) {
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}
		$argType = $scope->getType($methodCall->args[0]->value);
		if (!$argType instanceof ConstantStringType) {
			return new MixedType();
		}

		return new ObjectRepositoryType($argType->getValue(), $this->repositoryClass);
	}

}
