<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Collection;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\MethodTypeSpecifyingExtension;

final class IsEmptyTypeSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{

	private const COLLECTION_CLASS = 'Doctrine\Common\Collections\Collection';
	private const IS_EMPTY_METHOD_NAME = 'isEmpty';
	private const FIRST_METHOD_NAME = 'first';
	private const LAST_METHOD_NAME = 'last';

	/** @var TypeSpecifier */
	private $typeSpecifier;

	public function getClass(): string
	{
		return self::COLLECTION_CLASS;
	}

	public function isMethodSupported(
		MethodReflection $methodReflection,
		MethodCall $node,
		TypeSpecifierContext $context
	): bool
	{
		return (
			$methodReflection->getDeclaringClass()->getName() === self::COLLECTION_CLASS
			|| $methodReflection->getDeclaringClass()->isSubclassOf(self::COLLECTION_CLASS)
		)
		&& $methodReflection->getName() === self::IS_EMPTY_METHOD_NAME;
	}

	public function specifyTypes(
		MethodReflection $methodReflection,
		MethodCall $node,
		Scope $scope,
		TypeSpecifierContext $context
	): SpecifiedTypes
	{
		$first = $this->typeSpecifier->create(
			new MethodCall($node->var, self::FIRST_METHOD_NAME),
			new ConstantBooleanType(false),
			$context
		);

		$last = $this->typeSpecifier->create(
			new MethodCall($node->var, self::LAST_METHOD_NAME),
			new ConstantBooleanType(false),
			$context
		);

		return $first->unionWith($last);
	}

	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}

}
