<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\ObjectType;

class DqlRule implements Rule
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	/**
	 * @param \PhpParser\Node\Expr\MethodCall $node
	 * @param \PHPStan\Analyser\Scope $scope
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Node\Identifier) {
			return [];
		}

		if (count($node->args) === 0) {
			return [];
		}

		$dqlType = $scope->getType($node->args[0]->value);
		if (!$dqlType instanceof ConstantStringType) {
			return [];
		}

		$methodName = $node->name->toLowerString();
		if ($methodName !== 'createquery') {
			return [];
		}

		$calledOnType = $scope->getType($node->var);
		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!(new ObjectType($entityManagerInterface))->isSuperTypeOf($calledOnType)->yes()) {
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			throw new ShouldNotHappenException('Please provide the "objectManagerLoader" setting for the DQL validation.');
		}
		if (!$objectManager instanceof $entityManagerInterface) {
			return [];
		}

		/** @var EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$dql = $dqlType->getValue();
		$query = $objectManager->createQuery($dql);

		try {
			$query->getSQL();
		} catch (\Doctrine\ORM\Query\QueryException $e) {
			return [sprintf('DQL: %s', $e->getMessage())];
		}

		return [];
	}

}
