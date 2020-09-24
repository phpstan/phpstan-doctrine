<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function sprintf;

/**
 * @implements Rule<Node\Stmt\Class_>
 */
class EntityNotFinalRule implements Rule
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

	public function processNode(Node $node, Scope $scope): array
	{
		if (! $node->isFinal()) {
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return [];
		}

		$className = (string) $node->namespacedName;
		try {
			if ($objectManager->getMetadataFactory()->isTransient($className)) {
				return [];
			}
		} catch (\ReflectionException $e) {
			return [];
		}

		return [sprintf(
			'Entity class %s is final which can cause problems with proxies.',
			$className
		)];
	}

}
