<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;

class CreateQueryBuilderDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string|null */
	private $queryBuilderClass;

	public function __construct(?string $queryBuilderClass)
	{
		$this->queryBuilderClass = $queryBuilderClass;
	}

	public function getClass(): string
	{
		return 'Doctrine\ORM\EntityManager';
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
		return new QueryBuilderType(
			$this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder'
		);
	}

}
