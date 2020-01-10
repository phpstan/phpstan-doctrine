<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;

class GetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string */
	private $managerClass;

	/** @var ObjectMetadataResolver */
	private $metadataResolver;

	public function __construct(
		string $managerClass,
		ObjectMetadataResolver $metadataResolver
	)
	{
		$this->managerClass = $managerClass;
		$this->metadataResolver = $metadataResolver;
	}

	public function getClass(): string
	{
		return $this->managerClass;
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
			return $this->getDefaultReturnType($methodReflection);
		}
		$argType = $scope->getType($methodCall->args[0]->value);
		if ($argType instanceof ConstantStringType) {
			$objectName = $argType->getValue();
			$classType = new ObjectType($objectName);
		} elseif ($argType instanceof GenericClassStringType) {
			$classType = $argType->getGenericType();
			if (!$classType instanceof TypeWithClassName) {
				return $this->getDefaultReturnType($methodReflection);
			}

			$objectName = $classType->getClassName();
		} else {
			return $this->getDefaultReturnType($methodReflection);
		}

		$repositoryClass = $this->metadataResolver->getRepositoryClass($objectName);

		return new GenericObjectType($repositoryClass, [
			$classType,
		]);
	}

	private function getDefaultReturnType(MethodReflection $methodReflection): Type
	{
		$type = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		)->getReturnType();

		if ($type instanceof GenericObjectType) {
			$type = new GenericObjectType($type->getClassName(), [new ObjectWithoutClassType()]);
		}

		return $type;
	}

}
