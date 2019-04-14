<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use PHPStan\Broker\Broker;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\Dummy\DummyMethodReflection;

class EntityRepositoryClassReflectionExtension implements \PHPStan\Reflection\MethodsClassReflectionExtension, BrokerAwareExtension
{

	/** @var Broker */
	private $broker;

	public function setBroker(Broker $broker): void
	{
		$this->broker = $broker;
	}

	public function hasMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): bool
	{
		if (
			strpos($methodName, 'findBy') !== 0
			&& strpos($methodName, 'findOneBy') !== 0
			&& strpos($methodName, 'countBy') !== 0
		) {
			return false;
		}

		if ($classReflection->getName() === 'Doctrine\ORM\EntityRepository') {
			return true;
		}

		if (!$this->broker->hasClass('Doctrine\ORM\EntityRepository')) {
			return false;
		}

		return $classReflection->isSubclassOf('Doctrine\ORM\EntityRepository');
	}

	public function getMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): \PHPStan\Reflection\MethodReflection
	{
		return new DummyMethodReflection($methodName);
	}

}
