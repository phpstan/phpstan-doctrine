<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Collection;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Broker\Broker;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\BooleanType;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\TypeCombinator;

final class FirstTypeSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
	private const COLLECTION_CLASS = 'Doctrine\Common\Collections\Collection';
	private const IS_EMPTY_METHOD_NAME = 'isEmpty';
	private const FIRST_METHOD_NAME = 'first';

	private Broker $broker;
	private TypeSpecifier $typeSpecifier;

	public function __construct(Broker $broker)
	{
		$this->broker = $broker;
	}

	public function getClass(): string
	{
		return self::COLLECTION_CLASS;
	}

	public function isMethodSupported(
		MethodReflection $methodReflection,
		MethodCall $node,
		TypeSpecifierContext $context
	): bool {
		return $methodReflection->getName() === self::IS_EMPTY_METHOD_NAME && $context->false();
	}

	public function specifyTypes(
		MethodReflection $methodReflection,
		MethodCall $node,
		Scope $scope,
		TypeSpecifierContext $context
	): SpecifiedTypes {
		$classReflection = $this->broker->getClass(self::COLLECTION_CLASS);
		$methodVariants = $classReflection->getNativeMethod(self::FIRST_METHOD_NAME)->getVariants();

		return $this->typeSpecifier->create(
			new MethodCall($node->var, self::FIRST_METHOD_NAME),
			TypeCombinator::remove(new BooleanType(), ParametersAcceptorSelector::selectSingle($methodVariants)->getReturnType()),
			$context
		);
	}

	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}
}
