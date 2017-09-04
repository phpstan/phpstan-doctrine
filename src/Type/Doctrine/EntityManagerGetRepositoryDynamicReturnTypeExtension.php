<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\Entity;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class EntityManagerGetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/**
	 * @var \PHPStan\Broker\Broker
	 */
	private $broker;

	/**
	 * @var \Doctrine\Common\Annotations\Reader
	 */
	private $annotationReader;

	public function __construct(Broker $broker, Reader $annotationReader)
	{
		$this->broker = $broker;
		$this->annotationReader = $annotationReader;
	}

	public static function getClass(): string
	{
		return \Doctrine\ORM\EntityManager::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), [
			'getRepository',
		], true);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		if (count($methodCall->args) === 0) {
			return $methodReflection->getReturnType();
		}
		$arg = $methodCall->args[0]->value;
		if (!($arg instanceof \PhpParser\Node\Expr\ClassConstFetch)) {
			return $methodReflection->getReturnType();
		}

		$class = $arg->class;
		if (!($class instanceof \PhpParser\Node\Name)) {
			return $methodReflection->getReturnType();
		}

		$class = (string) $class;

		if ($class === 'static') {
			return $methodReflection->getReturnType();
		}

		if ($class === 'self') {
			$class = $scope->getClassReflection()->getName();
		}

		if (!$this->broker->hasClass($class)) {
			return $methodReflection->getReturnType();
		}

		$classReflection = $this->broker->getClass($class);
		$annotations = $this->annotationReader
			->getClassAnnotations($classReflection->getNativeReflection());

		foreach ($annotations as $annotation) {
			if (
				$annotation instanceof Entity &&
				$annotation->repositoryClass &&
				$this->broker->hasClass($annotation->repositoryClass)
			) {
				return new ObjectType($annotation->repositoryClass);
			}
		}

		return $methodReflection->getReturnType();
	}

}
