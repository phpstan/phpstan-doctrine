<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MissingPropertyFromReflectionException;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

/**
 * @implements Rule<Node\Stmt\PropertyProperty>
 */
class EntityEmbeddableRule implements Rule
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

		if (!isset($metadata->embeddedClasses[$propertyName])) {
			return [];
		}

		$errors = [];
		$embeddedClass = $metadata->embeddedClasses[$propertyName];
		$propertyWritableType = $property->getWritableType();
		$accordingToMapping = new ObjectType($embeddedClass['class']);
		if (!TypeCombinator::removeNull($propertyWritableType)->equals($accordingToMapping)) {
			$errors[] = sprintf(
				'Property %s::$%s type mapping mismatch: mapping specifies %s but property expects %s.',
				$class->getName(),
				$propertyName,
				$accordingToMapping->describe(VerbosityLevel::typeOnly()),
				$propertyWritableType->describe(VerbosityLevel::typeOnly())
			);
		}

		return $errors;
	}

}
