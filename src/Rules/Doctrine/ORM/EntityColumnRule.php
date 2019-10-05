<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

class EntityColumnRule implements Rule
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var \PHPStan\Type\Doctrine\DescriptorRegistry */
	private $descriptorRegistry;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver, DescriptorRegistry $descriptorRegistry)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->descriptorRegistry = $descriptorRegistry;
	}

	public function getNodeType(): string
	{
		return Node\Stmt\PropertyProperty::class;
	}

	/**
	 * @param \PhpParser\Node\Stmt\PropertyProperty $node
	 * @param \PHPStan\Analyser\Scope $scope
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$class = $scope->getClassReflection();
		if ($class === null) {
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return [];
		}

		$className = $class->getName();
		if ($objectManager->getMetadataFactory()->isTransient($className)) {
			return [];
		}

		/** @var \Doctrine\ORM\Mapping\ClassMetadataInfo $metadata */
		$metadata = $objectManager->getClassMetadata($className);
		$classMetadataInfo = 'Doctrine\ORM\Mapping\ClassMetadataInfo';
		if (!$metadata instanceof $classMetadataInfo) {
			return [];
		}

		$propertyName = (string) $node->name;
		try {
			$property = $class->getNativeProperty($propertyName);
		} catch (MissingPropertyFromReflectionException $e) {
			return [];
		}

		if (!isset($metadata->fieldMappings[$propertyName])) {
			return [];
		}
		$fieldMapping = $metadata->fieldMappings[$propertyName];

		$errors = [];
		try {
			$descriptor = $this->descriptorRegistry->get($fieldMapping['type']);
		} catch (DescriptorNotRegisteredException $e) {
			return [];
		}

		$writableToPropertyType = $descriptor->getWritableToPropertyType();
		$writableToDatabaseType = $descriptor->getWritableToDatabaseType();
		if ($fieldMapping['nullable'] === true) {
			$writableToPropertyType = TypeCombinator::addNull($writableToPropertyType);
			$writableToDatabaseType = TypeCombinator::addNull($writableToDatabaseType);
		}

		if (!$property->getWritableType()->isSuperTypeOf($writableToPropertyType)->yes()) {
			$errors[] = sprintf('Database can contain %s but property expects %s.', $writableToPropertyType->describe(VerbosityLevel::typeOnly()), $property->getWritableType()->describe(VerbosityLevel::typeOnly()));
		}
		if (!$writableToDatabaseType->isSuperTypeOf($property->getReadableType())->yes()) {
			$errors[] = sprintf('Property can contain %s but database expects %s.', $property->getReadableType()->describe(VerbosityLevel::typeOnly()), $writableToDatabaseType->describe(VerbosityLevel::typeOnly()));
		}
		return $errors;
	}

}
