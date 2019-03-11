<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;

class ObjectRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension, BrokerAwareExtension
{

	/** @var Broker */
	private $broker;

	public function setBroker(Broker $broker): void
	{
		$this->broker = $broker;
	}

	public function getClass(): string
	{
		return 'Doctrine\Common\Persistence\ObjectRepository';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		$methodName = $methodReflection->getName();
		return strpos($methodName, 'findBy') === 0
			|| strpos($methodName, 'findOneBy') === 0
			|| $methodName === 'findAll'
			|| $methodName === 'find';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		if (!$calledOnType instanceof TypeWithClassName) {
			return new MixedType();
		}

		$methodName = $methodReflection->getName();
		$repositoryClassReflection = $this->broker->getClass($calledOnType->getClassName());
		if (
			(
				(
					strpos($methodName, 'findBy') === 0
					&& strlen($methodName) > strlen('findBy')
				) || (
					strpos($methodName, 'findOneBy') === 0
					&& strlen($methodName) > strlen('findOneBy')
				)
			)
			&& $repositoryClassReflection->hasNativeMethod($methodName)
		) {
			return ParametersAcceptorSelector::selectFromArgs(
				$scope,
				$methodCall->args,
				$repositoryClassReflection->getNativeMethod($methodName)->getVariants()
			)->getReturnType();
		}

		if (!$calledOnType instanceof ObjectRepositoryType) {
			return new MixedType();
		}

		$entityType = new ObjectType($calledOnType->getEntityClass());

		if ($methodName === 'find' || strpos($methodName, 'findOneBy') === 0) {
			return TypeCombinator::addNull($entityType);
		}

		return new ArrayType(new IntegerType(), $entityType);
	}

}
