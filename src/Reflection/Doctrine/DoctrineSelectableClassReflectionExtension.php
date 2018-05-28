<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

class DoctrineSelectableClassReflectionExtension implements \PHPStan\Reflection\MethodsClassReflectionExtension, \PHPStan\Reflection\BrokerAwareExtension
{

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	public function setBroker(\PHPStan\Broker\Broker $broker): void
	{
		$this->broker = $broker;
	}

	public function hasMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): bool
	{
		return $classReflection->getName() === 'Doctrine\Common\Collections\Collection'
			&& $methodName === 'matching';
	}

	public function getMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): \PHPStan\Reflection\MethodReflection
	{
		$selectableReflection = $this->broker->getClass('Doctrine\Common\Collections\Selectable');
		return $selectableReflection->getNativeMethod($methodName);
	}

}
