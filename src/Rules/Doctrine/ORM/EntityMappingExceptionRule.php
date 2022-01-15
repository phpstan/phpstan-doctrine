<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

/**
 * @implements Rule<InClassNode>
 */
class EntityMappingExceptionRule implements Rule
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getNodeType(): string
	{
		return InClassNode::class;
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
			$objectManager->getClassMetadata($className);
		} catch (\Doctrine\ORM\Mapping\MappingException $e) {
			return [$e->getMessage()];
		}

		return [];
	}

}
