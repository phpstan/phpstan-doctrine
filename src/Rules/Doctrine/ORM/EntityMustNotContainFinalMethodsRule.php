<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function sprintf;

class EntityMustNotContainFinalMethodsRule implements Rule
{

	/** @var \PHPStan\Type\Doctrine\ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getNodeType(): string
	{
		return Node\Stmt\ClassMethod::class;
	}

	/**
	 * @param \PhpParser\Node\Stmt\ClassMethod $node
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

		if ($node->isFinal()) {
			return [
				sprintf(
					'Class %s is a Doctrine ORM entity, so its method %s() must not be marked as final.',
					$className,
					(string) $node->name
				),
			];
		}

		return [];
	}

}
