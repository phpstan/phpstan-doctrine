<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

class PropertiesExtension implements ReadWritePropertiesExtension
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
	{
		$className = $property->getDeclaringClass()->getName();
		$metadata = $this->findMetadata($className);
		if ($metadata === null) {
			return false;
		}

		return $metadata->hasField($propertyName) || $metadata->hasAssociation($propertyName);
	}

	/**
	 * @param class-string $className
	 * @return \Doctrine\ORM\Mapping\ClassMetadataInfo<object>
	 */
	private function findMetadata(string $className): ?\Doctrine\ORM\Mapping\ClassMetadataInfo
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return null;
		}

		try {
			if ($objectManager->getMetadataFactory()->isTransient($className)) {
				return null;
			}
		} catch (\ReflectionException $e) {
			return null;
		}

		try {
			$metadata = $objectManager->getClassMetadata($className);
		} catch (\Doctrine\ORM\Mapping\MappingException $e) {
			return null;
		}

		$classMetadataInfo = 'Doctrine\ORM\Mapping\ClassMetadataInfo';
		if (!$metadata instanceof $classMetadataInfo) {
			return null;
		}

		return $metadata;
	}

	public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->findMetadata($className);
		if ($metadata === null) {
			return false;
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		if ($metadata->isReadOnly && !$declaringClass->hasConstructor()) {
			return true;
		}

		if ($metadata->isIdentifierNatural()) {
			return false;
		}

		try {
			$identifiers = $metadata->getIdentifierFieldNames();
		} catch (\Throwable $e) {
			$mappingException = 'Doctrine\ORM\Mapping\MappingException';
			if (!$e instanceof $mappingException) {
				throw $e;
			}

			return false;
		}

		return in_array($propertyName, $identifiers, true);
	}

	public function isInitialized(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->findMetadata($className);
		if ($metadata === null) {
			return false;
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		return $metadata->isReadOnly && !$declaringClass->hasConstructor();
	}

}
