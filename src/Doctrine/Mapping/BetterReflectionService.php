<?php declare(strict_types = 1);

namespace PHPStan\Doctrine\Mapping;

use Doctrine\Persistence\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ReflectionService;
use PHPStan\Reflection\ReflectionProvider;
use ReflectionClass;

class BetterReflectionService implements ReflectionService
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	public function __construct(ReflectionProvider $reflectionProvider)
	{
		$this->reflectionProvider = $reflectionProvider;
	}

	public function getParentClasses($class)
	{
		if (!$this->reflectionProvider->hasClass($class)) {
			throw MappingException::nonExistingClass($class);
		}

		$classReflection = $this->reflectionProvider->getClass($class);

		return $classReflection->getParentClassesNames();
	}

	public function getClassShortName($class)
	{
		return $this->getClass($class)->getShortName();
	}

	public function getClassNamespace($class)
	{
		return $this->getClass($class)->getNamespaceName();
	}

	/**
	 * @param class-string<T> $class
	 * @return ReflectionClass<T>
	 *
	 * @template T of object
	 */
	public function getClass($class)
	{
		if (!$this->reflectionProvider->hasClass($class)) {
			throw MappingException::nonExistingClass($class);
		}

		$classReflection = $this->reflectionProvider->getClass($class);

		/** @var ReflectionClass<T> */
		return $classReflection->getNativeReflection();
	}

	public function getAccessibleProperty($class, $property)
	{
		$classReflection = $this->getClass($class);
		$property = $classReflection->getProperty($property);
		$property->setAccessible(true);

		return $property;
	}

	public function hasPublicMethod($class, $method)
	{
		$classReflection = $this->getClass($class);
		if (!$classReflection->hasMethod($method)) {
			return false;
		}

		return $classReflection->getMethod($method)->isPublic();
	}

}
