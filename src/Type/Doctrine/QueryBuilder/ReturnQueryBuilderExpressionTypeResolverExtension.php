<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use Doctrine\ORM\QueryBuilder;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use function count;
use function str_starts_with;

class ReturnQueryBuilderExpressionTypeResolverExtension implements ExpressionTypeResolverExtension
{

	/** @var OtherMethodQueryBuilderParser */
	private $otherMethodQueryBuilderParser;

	public function __construct(
		OtherMethodQueryBuilderParser $otherMethodQueryBuilderParser
	)
	{
		$this->otherMethodQueryBuilderParser = $otherMethodQueryBuilderParser;
	}

	public function getType(Expr $expr, Scope $scope): ?Type
	{
		if (!$expr instanceof MethodCall) {
			return null;
		}

		if (!$expr->name instanceof Identifier) {
			return null;
		}

		$queryBuilderType = new ObjectType(QueryBuilder::class);
		$callerType = $scope->getType($expr->var);

		foreach ($callerType->getObjectClassNames() as $callerObjectClassName) {
			if (str_starts_with($callerObjectClassName, 'Doctrine')) {
				return null; // do not dive into native Doctrine methods (like EntityRepository->createQueryBuilder)
			}
		}

		$methodReflection = $scope->getMethodReflection($callerType, $expr->name->name);

		if ($methodReflection === null) {
			return null;
		}

		$returnType = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();

		$returnsQueryBuilder = $queryBuilderType->isSuperTypeOf($returnType)->yes();

		if (!$returnsQueryBuilder) {
			return null;
		}

		$queryBuilderTypes = $this->otherMethodQueryBuilderParser->findQueryBuilderTypesInCalledMethod($scope, $expr);
		if (count($queryBuilderTypes) === 0) {
			return null;
		}

		return TypeCombinator::union(...$queryBuilderTypes);
	}

}
