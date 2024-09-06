<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use function get_class;
use function is_object;
use function method_exists;

class ExpressionBuilderDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private ObjectMetadataResolver $objectMetadataResolver;

	private ArgumentsProcessor $argumentsProcessor;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		ArgumentsProcessor $argumentsProcessor
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->argumentsProcessor = $argumentsProcessor;
	}

	public function getClass(): string
	{
		return 'Doctrine\ORM\Query\Expr';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return true;
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return null;
		}
		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!$objectManager instanceof $entityManagerInterface) {
			return null;
		}

		/** @var EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$queryBuilder = $objectManager->createQueryBuilder();

		try {
			$args = $this->argumentsProcessor->processArgs($scope, $methodReflection->getName(), $methodCall->getArgs());
		} catch (DynamicQueryBuilderArgumentException $e) {
			return null;
		}

		$calledOnType = $scope->getType($methodCall->var);
		if ($calledOnType instanceof ExprType) {
			$expr = $calledOnType->getExprObject();
		} else {
			$expr = $queryBuilder->expr();
		}

		if (!method_exists($expr, $methodReflection->getName())) {
			return null;
		}

		$exprValue = $expr->{$methodReflection->getName()}(...$args);
		if (is_object($exprValue)) {
			return new ExprType(get_class($exprValue), $exprValue);
		}

		return $scope->getTypeFromValue($exprValue);
	}

}
