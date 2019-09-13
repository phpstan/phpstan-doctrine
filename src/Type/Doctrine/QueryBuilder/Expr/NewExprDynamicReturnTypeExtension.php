<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\BrokerAwareExtension;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Type;

class NewExprDynamicReturnTypeExtension implements DynamicStaticMethodReturnTypeExtension, BrokerAwareExtension
{

	/** @var \PHPStan\Type\Doctrine\ArgumentsProcessor */
	private $argumentsProcessor;

	/** @var string */
	private $class;

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	public function __construct(
		ArgumentsProcessor $argumentsProcessor,
		string $class
	)
	{
		$this->argumentsProcessor = $argumentsProcessor;
		$this->class = $class;
	}

	public function setBroker(Broker $broker): void
	{
		$this->broker = $broker;
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

		$className = $scope->resolveName($methodCall->class);
		if (!$this->broker->hasClass($className)) {
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}

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
