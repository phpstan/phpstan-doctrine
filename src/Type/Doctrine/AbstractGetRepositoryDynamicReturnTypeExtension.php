<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Entity;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\BrokerAwareClassReflectionExtension;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

abstract class AbstractGetRepositoryDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension, BrokerAwareClassReflectionExtension
{

	/**
	 * @var \PHPStan\Broker\Broker
	 */
	private $broker;

	/**
	 * @var \Doctrine\Common\Annotations\Reader
	 */
	private $annotationReader;

	public function __construct(Reader $annotationReader)
	{
		$this->annotationReader = $annotationReader;
	}

	public function setBroker(\PHPStan\Broker\Broker $broker)
	{
		$this->broker = $broker;
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

		/** @var \Doctrine\ORM\Mapping\Entity|null $entityAnnotation */
		$entityAnnotation = null;
		foreach ($annotations as $annotation) {
			if ($annotation instanceof Entity) {
				$entityAnnotation = $annotation;
			}
		}

		if ($entityAnnotation === null) {
			return $methodReflection->getReturnType();
		}

		$repositoryClass = EntityRepository::class;
		if (
			$entityAnnotation->repositoryClass &&
			$this->broker->hasClass($entityAnnotation->repositoryClass)
		) {
			$repositoryClass = $entityAnnotation->repositoryClass;
		}

		return new ObjectType($repositoryClass);
	}

}
