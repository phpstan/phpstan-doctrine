<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use PHPStan\Broker\Broker;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;

class DoctrineSelectableClassReflectionExtension implements MethodsClassReflectionExtension, BrokerAwareExtension
{

	/** @var Broker */
	private $broker;

	public function setBroker(Broker $broker): void
	{
		$this->broker = $broker;
	}

	public function hasMethod(ClassReflection $classReflection, string $methodName): bool
	{
		return $classReflection->getName() === 'Doctrine\Common\Collections\Collection'
			&& $methodName === 'matching';
	}

	public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
	{
		$selectableReflection = $this->broker->getClass('Doctrine\Common\Collections\Selectable');
		return $selectableReflection->getNativeMethod($methodName);
	}

}
