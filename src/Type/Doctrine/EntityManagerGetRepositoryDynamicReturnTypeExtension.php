<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

class EntityManagerGetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string */
	private $repositoryClass;

	/** @var string */
	private $repositoryPattern;

	/** @var string */
	private $repositoryReplace;

	public function __construct(string $repositoryClass, string $repositoryPattern, string $repositoryReplace)
	{
		$this->repositoryClass = $repositoryClass;
		$this->repositoryPattern = $repositoryPattern;
		$this->repositoryReplace = $repositoryReplace;
	}

	public function getClass(): string
	{
		return 'Doctrine\Common\Persistence\ObjectManager';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getRepository';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		if (count($methodCall->args) === 0) {
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}

		$argType = $scope->getType($methodCall->args[0]->value);

		if (!$argType instanceof ConstantStringType) {
			return new MixedType();
		}

		$repositoryClass = preg_replace($this->repositoryPattern, $this->repositoryReplace, $argType->getValue());

		if (!$repositoryClass || !class_exists($repositoryClass)) {
			$repositoryClass = $this->repositoryClass;
		}

		return new EntityRepositoryType($argType->getValue(), $repositoryClass);
	}

}
