<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\Doctrine\DoctrineTypeUtils;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\Doctrine\Query\QueryType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function in_array;
use function method_exists;
use function strtolower;

class QueryBuilderGetQueryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var \PHPStan\Type\Doctrine\ArgumentsProcessor */
	private $argumentsProcessor;

	/** @var string|null */
	private $queryBuilderClass;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		ArgumentsProcessor $argumentsProcessor,
		?string $queryBuilderClass
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->argumentsProcessor = $argumentsProcessor;
		$this->queryBuilderClass = $queryBuilderClass;
	}

	public function getClass(): string
	{
		return $this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getQuery';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		$defaultReturnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$methodReflection->getVariants()
		)->getReturnType();
		$queryBuilderTypes = DoctrineTypeUtils::getQueryBuilderTypes($calledOnType);
		if (count($queryBuilderTypes) === 0) {
			return $defaultReturnType;
		}

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

		$resultTypes = [];
		foreach ($queryBuilderTypes as $queryBuilderType) {
			$queryBuilder = $objectManager->createQueryBuilder();

			foreach ($queryBuilderType->getMethodCalls() as $calledMethodCall) {
				if (!$calledMethodCall->name instanceof Identifier) {
					continue;
				}

				$methodName = $calledMethodCall->name->toString();
				$lowerMethodName = strtolower($methodName);
				if (in_array($lowerMethodName, [
					'setparameter',
					'setparameters',
				], true)) {
					continue;
				}

				if ($lowerMethodName === 'setfirstresult') {
					$queryBuilder->setFirstResult(0);
					continue;
				}

				if ($lowerMethodName === 'setmaxresults') {
					$queryBuilder->setMaxResults(10);
					continue;
				}

				if (!method_exists($queryBuilder, $methodName)) {
					continue;
				}

				try {
					$args = $this->argumentsProcessor->processArgs($scope, $methodName, $calledMethodCall->getArgs());
				} catch (DynamicQueryBuilderArgumentException $e) {
					return $defaultReturnType;
				}

				$queryBuilder->{$methodName}(...$args);
			}

			$resultTypes[] = new QueryType($queryBuilder->getDQL());
		}

		return TypeCombinator::union(...$resultTypes);
	}

}
