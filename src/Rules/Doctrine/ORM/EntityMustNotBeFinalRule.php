<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function sprintf;

class EntityMustNotBeFinalRule implements Rule
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getNodeType(): string
	{
		return Node\Stmt\Class_::class;
	}

	/**
	 * @param \PhpParser\Node\Stmt\Class_ $node
	 * @param \PHPStan\Analyser\Scope $scope
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return [];
		}

		$className = (string) $node->namespacedName;

		// not an entity
		if ($objectManager->getMetadataFactory()->isTransient($className)) {
			return [];
		}

		if ($node->isFinal()) {
			return [
				sprintf(
					'Class %s is a Doctrine ORM entity, so it should not be marked as final',
					$className
				),
			];
		}

		return [];
	}

}
