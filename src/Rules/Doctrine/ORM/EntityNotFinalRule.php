<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use function sprintf;

/**
 * @implements Rule<InClassNode>
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
		return InClassNode::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		$classReflection = $scope->getClassReflection();
		if ($classReflection === null) {
			throw new \PHPStan\ShouldNotHappenException();
		}
		if (!$classReflection->isFinalByKeyword()) {
			return [];
		}

		if ($this->objectMetadataResolver->isTransient($classReflection->getName())) {
			return [];
		}

		return [sprintf(
			'Entity class %s is final which can cause problems with proxies.',
			$classReflection->getDisplayName()
		)];
	}

}
