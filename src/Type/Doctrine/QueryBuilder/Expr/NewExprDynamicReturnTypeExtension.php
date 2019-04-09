<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

class NewExprDynamicReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{

	/** @var string */
	private $class;

	public function __construct(string $class)
	{
		$this->class = $class;
	}

	public function getClass(): string
	{
		return $this->class;
	}

	public function isStaticMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === '__construct';
	}

	public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): Type
	{
		if (!$methodCall->class instanceof Name) {
			throw new \PHPStan\ShouldNotHappenException();
		}
		return new ExprType($methodCall->class->toString(), $methodCall->args);
	}

}
