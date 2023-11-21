<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use Doctrine\ORM\QueryBuilder;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;

class QueryBuilderReturnedDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	/** @var OtherMethodQueryBuilderParser */
	private $otherMethodQueryBuilderParser;

	public function __construct(
		OtherMethodQueryBuilderParser $otherMethodQueryBuilderParser
	)
	{
		$this->otherMethodQueryBuilderParser = $otherMethodQueryBuilderParser;
	}

	public function getClass(): string
	{
		return 'QueryResult\CreateQuery\QueryBuilderGetQuery'; // TODO https://github.com/phpstan/phpstan-src/pull/2761
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		$returnType = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		)->getReturnType();

		if ($returnType instanceof MixedType) {
			return false;
		}

		return (new ObjectType(QueryBuilder::class))->isSuperTypeOf($returnType)->yes();
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$defaultReturnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$methodReflection->getVariants()
		)->getReturnType();

		$queryBuilderTypes = $this->otherMethodQueryBuilderParser->findQueryBuilderTypesInCalledMethod($scope, $methodCall);
		if (count($queryBuilderTypes) === 0) {
			return $defaultReturnType;
		}

		return TypeCombinator::union(...$queryBuilderTypes);
	}

}
