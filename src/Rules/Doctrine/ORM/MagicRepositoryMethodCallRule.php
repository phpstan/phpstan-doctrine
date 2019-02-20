<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Rules\Rule;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\ObjectRepositoryType;
use PHPStan\Type\VerbosityLevel;

class MagicRepositoryMethodCallRule implements Rule
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var Broker */
	private $broker;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver, Broker $broker)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->broker = $broker;
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
		$calledOnType = $scope->getType($node->var);
		if (!$calledOnType instanceof ObjectRepositoryType) {
			return [];
		}

		$methodNameIdentifier = $node->name;
		if (!$methodNameIdentifier instanceof Node\Identifier) {
			return [];
		}

		$methodName = $methodNameIdentifier->toString();
		if (
			strpos($methodName, 'findBy') === 0
			&& strlen($methodName) > strlen('findBy')
		) {
			$methodFieldName = substr($methodName, strlen('findBy'));
		} elseif (
			strpos($methodName, 'findOneBy') === 0
			&& strlen($methodName) > strlen('findOneBy')
		) {
			$methodFieldName = substr($methodName, strlen('findOneBy'));
		} elseif (
			strpos($methodName, 'countBy') === 0
			&& strlen($methodName) > strlen('countBy')
		) {
			$methodFieldName = substr($methodName, strlen('countBy'));
		} else {
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			throw new \PHPStan\ShouldNotHappenException(sprintf(
				'Please provide the "objectManagerLoader" setting for magic repository %s::%s() method validation.',
				$calledOnType->getClassName(),
				$methodName
			));
		}

		$fieldName = $this->classify($methodFieldName);
		$entityClass = $calledOnType->getEntityClass();
		$classMetadata = $objectManager->getClassMetadata($entityClass);
		if ($classMetadata->hasField($fieldName) || $classMetadata->hasAssociation($fieldName)) {
			return [];
		}

		$repositoryReflectionClass = $this->broker->getClass($calledOnType->getClassName());
		if ($repositoryReflectionClass->hasNativeMethod($methodName)) {
			return [];
		}

		return [sprintf(
			'Call to method %s::%s() - entity %s does not have a field named $%s.',
			$calledOnType->describe(VerbosityLevel::typeOnly()),
			$methodName,
			$entityClass,
			$fieldName
		)];
	}

	private function classify(string $word): string
	{
		return lcfirst(str_replace([' ', '_', '-'], '', ucwords($word, ' _-')));
	}

}
