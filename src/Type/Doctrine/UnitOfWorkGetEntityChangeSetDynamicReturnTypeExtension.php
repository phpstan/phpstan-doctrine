<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use function count;
use function is_array;
use function is_string;

final class UnitOfWorkGetEntityChangeSetDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private ObjectMetadataResolver $metadataResolver;

	private DescriptorRegistry $descriptorRegistry;

	public function __construct(
		ObjectMetadataResolver $metadataResolver,
		DescriptorRegistry $descriptorRegistry
	)
	{
		$this->metadataResolver = $metadataResolver;
		$this->descriptorRegistry = $descriptorRegistry;
	}

	public function getClass(): string
	{
		return UnitOfWork::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getEntityChangeSet';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		if (count($methodCall->getArgs()) === 0) {
			return $this->getDefaultReturnType($methodReflection, $scope, $methodCall);
		}

		$entityType = $scope->getType($methodCall->getArgs()[0]->value);
		$objectClassNames = $entityType->getObjectClassNames();

		if (count($objectClassNames) === 0) {
			return $this->getDefaultReturnType($methodReflection, $scope, $methodCall);
		}

		$changeSetTypes = [];

		foreach ($objectClassNames as $className) {
			$metadata = $this->metadataResolver->getClassMetadata($className);
			if ($metadata === null) {
				return $this->getDefaultReturnType($methodReflection, $scope, $methodCall);
			}

			$changeSetTypes[] = $this->createChangeSetType($metadata);
		}

		return TypeCombinator::union(...$changeSetTypes);
	}

	private function createChangeSetType(ClassMetadata $metadata): Type
	{
		$builder = ConstantArrayTypeBuilder::createEmpty();
		$collectionType = new ObjectType(PersistentCollection::class);

		foreach ($metadata->fieldMappings as $fieldName => $mapping) {
			if ($metadata->isIdentifier($fieldName)) {
				continue;
			}

			if (!isset($mapping['type'])) {
				continue;
			}

			try {
				$type = $this->descriptorRegistry->get($mapping['type'])->getWritableToPropertyType();
			} catch (DescriptorNotRegisteredException $exception) {
				$type = new MixedType();
			}

			if (($mapping['nullable'] ?? false) === true) {
				$type = TypeCombinator::addNull($type);
			}

			$fieldBuilder = ConstantArrayTypeBuilder::createEmpty();
			$fieldBuilder->setOffsetValueType(new ConstantIntegerType(0), $type);
			$fieldBuilder->setOffsetValueType(new ConstantIntegerType(1), $type);

			$builder->setOffsetValueType(
				new ConstantStringType($fieldName),
				$fieldBuilder->getArray()
			);
		}

		foreach ($metadata->associationMappings as $fieldName => $mapping) {
			if (($mapping['type'] & ClassMetadata::TO_ONE) !== 0) {
				$targetEntity = $mapping['targetEntity'] ?? null;
				if (!is_string($targetEntity)) {
					continue;
				}

				$type = new ObjectType($targetEntity);
				if ($this->isAssociationNullable($mapping)) {
					$type = TypeCombinator::addNull($type);
				}

				$fieldBuilder = ConstantArrayTypeBuilder::createEmpty();
				$fieldBuilder->setOffsetValueType(new ConstantIntegerType(0), $type);
				$fieldBuilder->setOffsetValueType(new ConstantIntegerType(1), $type);

				$builder->setOffsetValueType(
					new ConstantStringType($fieldName),
					$fieldBuilder->getArray()
				);
				continue;
			}

			if (($mapping['type'] & ClassMetadata::TO_MANY) === 0) {
				continue;
			}

			$fieldBuilder = ConstantArrayTypeBuilder::createEmpty();
			$fieldBuilder->setOffsetValueType(new ConstantIntegerType(0), $collectionType);
			$fieldBuilder->setOffsetValueType(new ConstantIntegerType(1), $collectionType);

			$builder->setOffsetValueType(
				new ConstantStringType($fieldName),
				$fieldBuilder->getArray()
			);
		}

		return $builder->getArray();
	}

	/**
	 * @param array<string, mixed> $association
	 */
	private function isAssociationNullable(array $association): bool
	{
		$joinColumns = $association['joinColumns'] ?? null;
		if (!is_array($joinColumns)) {
			return true;
		}

		foreach ($joinColumns as $joinColumn) {
			if (!is_array($joinColumn)) {
				continue;
			}
			if (($joinColumn['nullable'] ?? true) === false) {
				return false;
			}
		}

		return true;
	}

	private function getDefaultReturnType(MethodReflection $methodReflection, Scope $scope, MethodCall $methodCall): Type
	{
		return ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$methodReflection->getVariants()
		)->getReturnType();
	}

}
