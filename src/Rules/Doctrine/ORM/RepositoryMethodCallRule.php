<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\ObjectRepositoryType;
use PHPStan\Type\VerbosityLevel;

class RepositoryMethodCallRule implements Rule
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
	 * @param Scope $scope
	 * @return string[]
	 */
	public function processNode(Node $node, Scope $scope): array
	{
		if (!isset($node->args[0])) {
			return [];
		}
		$argType = $scope->getType($node->args[0]->value);
		if (!$argType instanceof ConstantArrayType) {
			return [];
		}
		if (count($argType->getKeyTypes()) === 0) {
			return [];
		}
		$calledOnType = $scope->getType($node->var);
		if (!$calledOnType instanceof ObjectRepositoryType) {
			return [];
		}

		$methodNameIdentifier = $node->name;
		if (!$methodNameIdentifier instanceof Node\Identifier) {
			return [];
		}

		$methodName = $methodNameIdentifier->toString();
		if (!in_array($methodName, [
			'findBy',
			'findOneBy',
			'count',
		], true)) {
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return [];
		}

		$entityClass = $calledOnType->getEntityClass();
		$classMetadata = $objectManager->getClassMetadata($entityClass);

		$messages = [];
		foreach ($argType->getKeyTypes() as $keyType) {
			if (!$keyType instanceof ConstantStringType) {
				continue;
			}

			$fieldName = $keyType->getValue();
			if (
				$classMetadata->hasField($fieldName)
				|| $classMetadata->hasAssociation($fieldName)
			) {
				continue;
			}

			$messages[] = sprintf(
				'Call to method %s::%s() - entity %s does not have a field named $%s.',
				$calledOnType->describe(VerbosityLevel::typeOnly()),
				$methodName,
				$entityClass,
				$fieldName
			);
		}

		return $messages;
	}

}
