<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\QueryBuilder\QueryBuilderType;
use PHPStan\Type\ObjectType;

class QueryBuilderDqlRule implements Rule
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var bool */
	private $reportDynamicQueryBuilders;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		bool $reportDynamicQueryBuilders
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->reportDynamicQueryBuilders = $reportDynamicQueryBuilders;
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
		if (!$node->name instanceof Node\Identifier) {
			return [];
		}

		if ($node->name->toLowerString() !== 'getquery') {
			return [];
		}

		$calledOnType = $scope->getType($node->var);
		if (!$calledOnType instanceof QueryBuilderType) {
			if (
				$this->reportDynamicQueryBuilders
				&& (new ObjectType('Doctrine\ORM\QueryBuilder'))->isSuperTypeOf($calledOnType)->yes()
			) {
				return [
					'Could not analyse QueryBuilder with unknown beginning.',
				];
			}
			return [];
		}

		try {
			$dqlType = $scope->getType(new MethodCall($node, new Node\Identifier('getDQL'), []));
		} catch (\Throwable $e) {
			return [
				'foo',
				// todo reproduce
			];
		}

		if (!$dqlType instanceof ConstantStringType) {
			if ($this->reportDynamicQueryBuilders) {
				return [
					'Could not analyse QueryBuilder with dynamic arguments.',
				];
			}
			return [];
		}

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			throw new ShouldNotHappenException('Please provide the "objectManagerLoader" setting for the DQL validation.');
		}

		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!$objectManager instanceof $entityManagerInterface) {
			return [];
		}

		/** @var \Doctrine\ORM\EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		try {
			$objectManager->createQuery($dqlType->getValue())->getSQL();
		} catch (\Doctrine\ORM\Query\QueryException $e) {
			$message = sprintf('QueryBuilder: %s', $e->getMessage());
			if (strpos($e->getMessage(), '[Syntax Error]') === 0) {
				$message .= sprintf("\nDQL: %s", $dqlType->getValue());
			}

			return [$message];
		}

		return [];
	}

}
