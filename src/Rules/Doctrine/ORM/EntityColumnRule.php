<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\ErrorType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\VerbosityLevel;
use Throwable;

use function in_array;
use function sprintf;

/**
 * @implements Rule<Node\Stmt\PropertyProperty>
 */
class EntityColumnRule implements Rule
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var \PHPStan\Type\Doctrine\DescriptorRegistry */
	private $descriptorRegistry;

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var bool */
	private $reportUnknownTypes;

	/** @var bool */
	private $allowNullablePropertyForRequiredField;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		DescriptorRegistry $descriptorRegistry,
		ReflectionProvider $reflectionProvider,
		bool $reportUnknownTypes,
		bool $allowNullablePropertyForRequiredField
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->descriptorRegistry = $descriptorRegistry;
		$this->reflectionProvider = $reflectionProvider;
		$this->reportUnknownTypes = $reportUnknownTypes;
		$this->allowNullablePropertyForRequiredField = $allowNullablePropertyForRequiredField;
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

		if (!isset($metadata->fieldMappings[$propertyName])) {
			return [];
		}

		/** @var array{type: string, fieldName: string, columnName?: string, inherited?: class-string, nullable?: bool, enumType?: ?string} $fieldMapping */
		$fieldMapping = $metadata->fieldMappings[$propertyName];

		$errors = [];
		try {
			$descriptor = $this->descriptorRegistry->get($fieldMapping['type']);
		} catch (DescriptorNotRegisteredException $e) {
			return $this->reportUnknownTypes ? [sprintf(
				'Property %s::$%s: Doctrine type "%s" does not have any registered descriptor.',
				$className,
				$propertyName,
				$fieldMapping['type']
			)] : [];
		}

		$writableToPropertyType = $descriptor->getWritableToPropertyType();
		$writableToDatabaseType = $descriptor->getWritableToDatabaseType();

		$enumTypeString = $fieldMapping['enumType'] ?? null;
		if ($enumTypeString !== null) {
			if ($this->reflectionProvider->hasClass($enumTypeString)) {
				$enumReflection = $this->reflectionProvider->getClass($enumTypeString);
				$backedEnumType = $enumReflection->getBackedEnumType();
				if ($backedEnumType !== null) {
					if (!$backedEnumType->equals($writableToDatabaseType) || !$backedEnumType->equals($writableToPropertyType)) {
						$errors[] = sprintf(
							'Property %s::$%s type mapping mismatch: backing type %s of enum %s does not match database type %s.',
							$className,
							$propertyName,
							$backedEnumType->describe(VerbosityLevel::typeOnly()),
							$enumReflection->getDisplayName(),
							$writableToDatabaseType->describe(VerbosityLevel::typeOnly())
						);
					}
				}
			}
			$enumType = new ObjectType($enumTypeString);
			$writableToPropertyType = $enumType;
			$writableToDatabaseType = $enumType;
		}

		$identifiers = [];
		if ($metadata->generatorType !== 5) { // ClassMetadataInfo::GENERATOR_TYPE_NONE
			try {
				$identifiers = $metadata->getIdentifierFieldNames();
			} catch (Throwable $e) {
				$mappingException = 'Doctrine\ORM\Mapping\MappingException';
				if (!$e instanceof $mappingException) {
					throw $e;
				}
			}
		}

		$nullable = isset($fieldMapping['nullable']) ? $fieldMapping['nullable'] === true : false;
		if ($nullable) {
			$writableToPropertyType = TypeCombinator::addNull($writableToPropertyType);
			$writableToDatabaseType = TypeCombinator::addNull($writableToDatabaseType);
		}

		$propertyWritableType = $property->getWritableType();
		if (get_class($propertyWritableType) === MixedType::class || $propertyWritableType instanceof ErrorType || $propertyWritableType instanceof NeverType) {
			return [];
		}

		$transformArrays = function (Type $type, callable $traverse): Type {
			if ($type instanceof ArrayType) {
				return new ArrayType(new MixedType(), new MixedType());
			}

			return $traverse($type);
		};

		$propertyWritableType = TypeTraverser::map($propertyWritableType, $transformArrays);

		if (!$propertyWritableType->isSuperTypeOf($writableToPropertyType)->yes()) {
			$errors[] = sprintf(
				'Property %s::$%s type mapping mismatch: database can contain %s but property expects %s.',
				$className,
				$propertyName,
				$writableToPropertyType->describe(VerbosityLevel::getRecommendedLevelByType($propertyWritableType, $writableToPropertyType)),
				$property->getWritableType()->describe(VerbosityLevel::getRecommendedLevelByType($propertyWritableType, $writableToPropertyType))
			);
		}
		$propertyReadableType = TypeTraverser::map($property->getReadableType(), $transformArrays);

		if (
			!$writableToDatabaseType->isSuperTypeOf(
				$this->allowNullablePropertyForRequiredField || (in_array($propertyName, $identifiers, true) && !$nullable)
					? TypeCombinator::removeNull($propertyReadableType)
					: $propertyReadableType
			)->yes()
		) {
			$errors[] = sprintf(
				'Property %s::$%s type mapping mismatch: property can contain %s but database expects %s.',
				$className,
				$propertyName,
				$propertyReadableType->describe(VerbosityLevel::getRecommendedLevelByType($writableToDatabaseType, $propertyReadableType)),
				$writableToDatabaseType->describe(VerbosityLevel::getRecommendedLevelByType($writableToDatabaseType, $propertyReadableType))
			);
		}
		return $errors;
	}

}
