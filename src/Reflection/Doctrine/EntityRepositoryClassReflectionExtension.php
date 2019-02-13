<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use PHPStan\Reflection\Dummy\DummyMethodReflection;

class EntityRepositoryClassReflectionExtension implements \PHPStan\Reflection\MethodsClassReflectionExtension
{

	public function hasMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): bool
	{
		return $classReflection->getName() === 'Doctrine\ORM\EntityRepository'
			&& (strpos($methodName, 'findBy') === 0 || strpos($methodName, 'findOneBy') === 0);
	}

	public function getMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): \PHPStan\Reflection\MethodReflection
	{
		return new DummyMethodReflection($methodName);
	}

}
