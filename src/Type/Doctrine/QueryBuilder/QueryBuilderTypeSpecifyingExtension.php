<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Doctrine\DoctrineTypeUtils;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

class QueryBuilderTypeSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{

	/** @var string|null */
	private $queryBuilderClass;

	/** @var \PHPStan\Analyser\TypeSpecifier */
	private $typeSpecifier;

	public function __construct(?string $queryBuilderClass)
	{
		$this->queryBuilderClass = $queryBuilderClass;
	}

	public function getClass(): string
	{
		return $this->queryBuilderClass ?? 'Doctrine\ORM\QueryBuilder';
	}

	public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
	{
		$this->typeSpecifier = $typeSpecifier;
	}

	public function isMethodSupported(MethodReflection $methodReflection, MethodCall $node, TypeSpecifierContext $context): bool
	{
		return $context->null();
	}

	public function specifyTypes(MethodReflection $methodReflection, MethodCall $node, Scope $scope, TypeSpecifierContext $context): SpecifiedTypes
	{
		if (!$scope->isInFirstLevelStatement()) {
			return new SpecifiedTypes([]);
		}
		if (!$node->name instanceof Identifier) {
			return new SpecifiedTypes([]);
		}

		$lowerMethodName = strtolower($node->name->toString());
		if (in_array($lowerMethodName, [
			'setparameter',
			'setparameters',
		], true)) {
			return new SpecifiedTypes([]);
		}

		$returnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$node->args,
			$methodReflection->getVariants()
		)->getReturnType();
		if ($returnType instanceof MixedType) {
			return new SpecifiedTypes([]);
		}
		if (!$returnType->isSuperTypeOf(new ObjectType($this->getClass()))->yes()) {
			return new SpecifiedTypes([]);
		}

		$calledOnType = $scope->getType($node->var);
		$queryBuilderTypes = DoctrineTypeUtils::getQueryBuilderTypes($calledOnType);
		if (count($queryBuilderTypes) === 0) {
			return new SpecifiedTypes([]);
		}

		$queryBuilderNode = $node;
		while ($queryBuilderNode instanceof MethodCall) {
			$queryBuilderNode = $queryBuilderNode->var;
		}

		$resultTypes = [];
		foreach ($queryBuilderTypes as $queryBuilderType) {
			$resultTypes[] = $queryBuilderType->append($node);
		}

		return $this->typeSpecifier->create(
			$queryBuilderNode,
			TypeCombinator::union(...$resultTypes),
			TypeSpecifierContext::createTruthy(),
			true
		);
	}

}
