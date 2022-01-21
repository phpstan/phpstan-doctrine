<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\ORM\EntityRepository;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;

class GetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var string|null */
	private $repositoryClass;

	/** @var string|null */
	private $ormRepositoryClass;

	/** @var string|null */
	private $odmRepositoryClass;

	/** @var string */
	private $managerClass;

	/** @var ObjectMetadataResolver */
	private $metadataResolver;

	public function __construct(
		ReflectionProvider $reflectionProvider,
		?string $repositoryClass,
		?string $ormRepositoryClass,
		?string $odmRepositoryClass,
		string $managerClass,
		ObjectMetadataResolver $metadataResolver
	)
	{
		$this->reflectionProvider = $reflectionProvider;
		$this->repositoryClass = $repositoryClass;
		$this->ormRepositoryClass = $ormRepositoryClass;
		$this->odmRepositoryClass = $odmRepositoryClass;
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
		$calledOnType = $scope->getType($methodCall->var);
		if ((new ObjectType(DocumentManager::class))->isSuperTypeOf($calledOnType)->yes()) {
			$defaultRepositoryClass = $this->odmRepositoryClass ?? $this->repositoryClass ?? DocumentRepository::class;
		} else {
			$defaultRepositoryClass = $this->ormRepositoryClass ?? $this->repositoryClass ?? EntityRepository::class;
		}
		if (count($methodCall->getArgs()) === 0) {
			return new GenericObjectType(
				$defaultRepositoryClass,
				[new ObjectWithoutClassType()]
			);
		}
		$argType = $scope->getType($methodCall->getArgs()[0]->value);
		if ($argType instanceof ConstantStringType) {
			$objectName = $argType->getValue();
			$classType = new ObjectType($objectName);
		} elseif ($argType instanceof GenericClassStringType) {
			$classType = $argType->getGenericType();
			if (!$classType instanceof TypeWithClassName) {
				return new GenericObjectType(
					$defaultRepositoryClass,
					[$classType]
				);
			}

			$objectName = $classType->getClassName();
		} else {
			return $this->getDefaultReturnType($scope, $methodCall->getArgs(), $methodReflection, $defaultRepositoryClass);
		}

		try {
			$repositoryClass = $this->getRepositoryClass($objectName, $defaultRepositoryClass);
		} catch (\Doctrine\ORM\Mapping\MappingException $e) {
			return $this->getDefaultReturnType($scope, $methodCall->getArgs(), $methodReflection, $defaultRepositoryClass);
		}

		return new GenericObjectType($repositoryClass, [
			$classType,
		]);
	}

	/**
	 * @param \PHPStan\Analyser\Scope $scope
	 * @param \PhpParser\Node\Arg[] $args
	 * @param \PHPStan\Reflection\MethodReflection $methodReflection
	 * @return \PHPStan\Type\Type
	 */
	private function getDefaultReturnType(Scope $scope, array $args, MethodReflection $methodReflection, string $defaultRepositoryClass): Type
	{
		$defaultType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$args,
			$methodReflection->getVariants()
		)->getReturnType();
		if ($defaultType instanceof GenericObjectType && count($defaultType->getTypes()) > 0) {
			return new GenericObjectType(
				$defaultRepositoryClass,
				[$defaultType->getTypes()[0]]
			);
		}

		return $defaultType;
	}

	private function getRepositoryClass(string $className, string $defaultRepositoryClass): string
	{
		if (!$this->reflectionProvider->hasClass($className)) {
			return $defaultRepositoryClass;
		}

		$classReflection = $this->reflectionProvider->getClass($className);
		if ($classReflection->isInterface() || $classReflection->isTrait()) {
			return $defaultRepositoryClass;
		}

		$metadata = $this->metadataResolver->getClassMetadata($classReflection->getName());
		if ($metadata !== null) {
			return $metadata->customRepositoryClassName ?? $defaultRepositoryClass;
		}

		$objectManager = $this->metadataResolver->getObjectManager();
		if ($objectManager === null) {
			return $defaultRepositoryClass;
		}

		$metadata = $objectManager->getClassMetadata($classReflection->getName());
		$odmMetadataClass = 'Doctrine\ODM\MongoDB\Mapping\ClassMetadata';
		if ($metadata instanceof $odmMetadataClass) {
			/** @var \Doctrine\ODM\MongoDB\Mapping\ClassMetadata<object> $odmMetadata */
			$odmMetadata = $metadata;
			return $odmMetadata->customRepositoryClassName ?? $defaultRepositoryClass;
		}

		return $defaultRepositoryClass;
	}

}
