<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class ManagerRegistryGetManagerDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string */
	private $registryClass;

	/** @var string */
	private $managerClass;

	public function __construct(string $registryClass, string $managerClass)
	{
		$this->registryClass = $registryClass;
		$this->managerClass = $managerClass;
	}

	public function getClass(): string
	{
		return $this->registryClass;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), [
			'getManager',
			'getManagers',
			'resetManager',
			'getManagerForClass',
		], true);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$managerType = new ObjectType($this->managerClass);
		switch ($methodReflection->getName()) {
			case 'getManagerForClass':
				return TypeCombinator::addNull($managerType);

			case 'getManagers':
				return new ArrayType(new IntegerType(), $managerType);

			default:
				return $managerType;
		}
	}

}
