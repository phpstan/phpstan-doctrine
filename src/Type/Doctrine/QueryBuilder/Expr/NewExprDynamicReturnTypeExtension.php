<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

class NewExprDynamicReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension
{

	/** @var \PHPStan\Type\Doctrine\ArgumentsProcessor */
	private $argumentsProcessor;

	/** @var string */
	private $class;

	public function __construct(
		ArgumentsProcessor $argumentsProcessor,
		string $class
	)
	{
		$this->argumentsProcessor = $argumentsProcessor;
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

		$className = $methodCall->class->toString();

		try {
			$exprObject = new $className(
				...$this->argumentsProcessor->processArgs(
					$scope,
					$methodReflection->getName(),
					$methodCall->args
				)
			);
		} catch (DynamicQueryBuilderArgumentException $e) {
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}

		return new ExprType($className, $exprObject);
	}

}
