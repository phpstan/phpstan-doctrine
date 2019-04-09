<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use Doctrine\ORM\Query\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

class ExpressionBuilderDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var \PHPStan\Type\Doctrine\ArgumentsProcessor */
	private $argumentsProcessor;

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
		return Expr::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return true;
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$defaultReturnType = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return $defaultReturnType;
		}
		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!$objectManager instanceof $entityManagerInterface) {
			return $defaultReturnType;
		}

		/** @var \Doctrine\ORM\EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$queryBuilder = $objectManager->createQueryBuilder();

		try {
			$args = $this->argumentsProcessor->processArgs($scope, $methodReflection->getName(), $methodCall->args);
		} catch (DynamicQueryBuilderArgumentException $e) {
			return $defaultReturnType;
		}

		$exprObject = $queryBuilder->expr()->{$methodReflection->getName()}(...$args);

		return new ExprType(get_class($exprObject), $exprObject);
	}

}
