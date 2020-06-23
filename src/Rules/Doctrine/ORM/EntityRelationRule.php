<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\ErrorType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

/**
 * @implements Rule<Node\Stmt\PropertyProperty>
 */
class EntityRelationRule implements Rule
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getNodeType(): string
	{
		return Node\Stmt\PropertyProperty::class;
	}

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
		try {
			if ($objectManager->getMetadataFactory()->isTransient($className)) {
				return [];
			}
		} catch (\ReflectionException $e) {
			return [];
		}

		try {
			$metadata = $objectManager->getClassMetadata($className);
		} catch (\Doctrine\ORM\Mapping\MappingException $e) {
			return [];
		}

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

		if (!isset($metadata->associationMappings[$propertyName])) {
			return [];
		}
		$associationMapping = $metadata->associationMappings[$propertyName];
		$identifiers = [];
		try {
			$identifiers = $metadata->getIdentifierFieldNames();
		} catch (\Throwable $e) {
			$mappingException = 'Doctrine\ORM\Mapping\MappingException';
			if (!$e instanceof $mappingException) {
				throw $e;
			}
		}

		$columnType = null;
		$toMany = false;
		if ((bool) ($associationMapping['type'] & 3)) { // ClassMetadataInfo::TO_ONE
			$columnType = new ObjectType($associationMapping['targetEntity']);
			if (in_array($propertyName, $identifiers, true)) {
				$nullable = false;
			} else {
				$nullable = $associationMapping['joinColumns'][0]['nullable'] ?? true;
			}
			if ($nullable) {
				$columnType = TypeCombinator::addNull($columnType);
			}
		} elseif ((bool) ($associationMapping['type'] & 12)) { // ClassMetadataInfo::TO_MANY
			$toMany = true;
			$columnType = TypeCombinator::intersect(
				new ObjectType('Doctrine\Common\Collections\Collection'),
				new IterableType(new MixedType(), new ObjectType($associationMapping['targetEntity']))
			);
		}

		$errors = [];
		if ($columnType !== null) {
			$propertyWritableType = $property->getWritableType();
			if (get_class($propertyWritableType) === MixedType::class || $propertyWritableType instanceof ErrorType || $propertyWritableType instanceof NeverType) {
				return [];
			}

			$collectionObjectType = new ObjectType('Doctrine\Common\Collections\Collection');
			$propertyWritableTypeToCheckAgainst = $propertyWritableType;
			if (
				$toMany
				&& $collectionObjectType->isSuperTypeOf($propertyWritableType)->yes()
				&& $propertyWritableType->isIterable()->yes()
			) {
				$propertyWritableTypeToCheckAgainst = TypeCombinator::intersect(
					$collectionObjectType,
					new IterableType(new MixedType(true), $propertyWritableType->getIterableValueType())
				);
			}
			if (!$propertyWritableTypeToCheckAgainst->isSuperTypeOf($columnType)->yes()) {
				$errors[] = sprintf(
					'Property %s::$%s type mapping mismatch: database can contain %s but property expects %s.',
					$className,
					$propertyName,
					$columnType->describe(VerbosityLevel::typeOnly()),
					$propertyWritableType->describe(VerbosityLevel::typeOnly())
				);
			}
			if (!$columnType->isSuperTypeOf($property->getReadableType())->yes()) {
				$errors[] = sprintf(
					'Property %s::$%s type mapping mismatch: property can contain %s but database expects %s.',
					$className,
					$propertyName,
					$property->getReadableType()->describe(VerbosityLevel::typeOnly()),
					$columnType->describe(VerbosityLevel::typeOnly())
				);
			}
		}

		return $errors;
	}

}
