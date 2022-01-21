<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use Throwable;
use function in_array;

class PropertiesExtension implements ReadWritePropertiesExtension
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
	{
		$className = $property->getDeclaringClass()->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return false;
		}

		return $metadata->hasField($propertyName) || $metadata->hasAssociation($propertyName);
	}

	public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
	{
		$declaringClass = $property->getDeclaringClass();
		$className = $declaringClass->getName();
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
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
		} catch (Throwable $e) {
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
		$metadata = $this->objectMetadataResolver->getClassMetadata($className);
		if ($metadata === null) {
			return false;
		}

		if (!$metadata->hasField($propertyName) && !$metadata->hasAssociation($propertyName)) {
			return false;
		}

		return $metadata->isReadOnly && !$declaringClass->hasConstructor();
	}

}
