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
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}
		$argType = $scope->getType($methodCall->args[0]->value);
		if ($argType instanceof ConstantStringType) {
			$objectName = $argType->getValue();
			$classType = new ObjectType($objectName);
		} elseif ($argType instanceof GenericClassStringType) {
			$classType = $argType->getGenericType();
			if (!$classType instanceof TypeWithClassName) {
				return ParametersAcceptorSelector::selectSingle(
					$methodReflection->getVariants()
				)->getReturnType();
			}

			$objectName = $classType->getClassName();
		} else {
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}

		$repositoryClass = $this->metadataResolver->getRepositoryClass($objectName);

		return new GenericObjectType($repositoryClass, [
			$classType,
		]);
	}

}
