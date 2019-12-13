<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use Doctrine\Common\Persistence\ObjectRepository;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeWithClassName;

class EntityRepositoryClassReflectionExtension implements \PHPStan\Reflection\MethodsClassReflectionExtension
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function hasMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): bool
	{
		if (
			strpos($methodName, 'findBy') === 0
			&& strlen($methodName) > strlen('findBy')
		) {
			$methodFieldName = substr($methodName, strlen('findBy'));
		} elseif (
			strpos($methodName, 'findOneBy') === 0
			&& strlen($methodName) > strlen('findOneBy')
		) {
			$methodFieldName = substr($methodName, strlen('findOneBy'));
		} elseif (
			strpos($methodName, 'countBy') === 0
			&& strlen($methodName) > strlen('countBy')
		) {
			$methodFieldName = substr($methodName, strlen('countBy'));
		} else {
			return false;
		}

		$repositoryAncesor = $classReflection->getAncestorWithClassName(ObjectRepository::class);
		if ($repositoryAncesor === null) {
			$repositoryAncesor = $classReflection->getAncestorWithClassName(\Doctrine\Persistence\ObjectRepository::class);
			if ($repositoryAncesor === null) {
				return false;
			}
		}

		$templateTypeMap = $repositoryAncesor->getActiveTemplateTypeMap();
		$entityClassType = $templateTypeMap->getType('TEntityClass');
		if ($entityClassType === null) {
			return false;
		}

		if (!$entityClassType instanceof TypeWithClassName) {
			return false;
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return false;
		}

		$fieldName = $this->classify($methodFieldName);
		$classMetadata = $objectManager->getClassMetadata($entityClassType->getClassName());

		return $classMetadata->hasField($fieldName) || $classMetadata->hasAssociation($fieldName);
	}

	private function classify(string $word): string
	{
		return lcfirst(str_replace([' ', '_', '-'], '', ucwords($word, ' _-')));
	}

	public function getMethod(\PHPStan\Reflection\ClassReflection $classReflection, string $methodName): \PHPStan\Reflection\MethodReflection
	{
		$repositoryAncesor = $classReflection->getAncestorWithClassName(ObjectRepository::class);
		if ($repositoryAncesor === null) {
			$repositoryAncesor = $classReflection->getAncestorWithClassName(\Doctrine\Persistence\ObjectRepository::class);
			if ($repositoryAncesor === null) {
				throw new \PHPStan\ShouldNotHappenException();
			}
		}

		$templateTypeMap = $repositoryAncesor->getActiveTemplateTypeMap();
		$entityClassType = $templateTypeMap->getType('TEntityClass');
		if ($entityClassType === null) {
			throw new \PHPStan\ShouldNotHappenException();
		}

		if (
			strpos($methodName, 'findBy') === 0
		) {
			$returnType = new ArrayType(new IntegerType(), $entityClassType);
		} elseif (
			strpos($methodName, 'findOneBy') === 0
		) {
			$returnType = TypeCombinator::union($entityClassType, new NullType());
		} elseif (
			strpos($methodName, 'countBy') === 0
		) {
			$returnType = new IntegerType();
		} else {
			throw new \PHPStan\ShouldNotHappenException();
		}

		return new MagicRepositoryMethodReflection($repositoryAncesor, $methodName, $returnType);
	}

}
