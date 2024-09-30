<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

class CreateQueryBuilderDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private ?string $queryBuilderClass = null;

	public function __construct(
		?string $queryBuilderClass
	)
	{
		$this->queryBuilderClass = $queryBuilderClass;
	}

	public function getClass(): string
	{
		return 'Doctrine\ORM\EntityManagerInterface';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'createQueryBuilder';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		return new BranchingQueryBuilderType(
			$this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder',
		);
	}

}
