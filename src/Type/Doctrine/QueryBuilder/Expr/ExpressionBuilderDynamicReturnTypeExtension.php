<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Accessory\AccessoryLiteralStringType;
use PHPStan\Type\Accessory\AccessoryNonEmptyStringType;
use PHPStan\Type\Doctrine\ArgumentsProcessor;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use function count;
use function get_class;
use function in_array;
use function is_object;
use function method_exists;

class ExpressionBuilderDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var ArgumentsProcessor */
	private $argumentsProcessor;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		ArgumentsProcessor $argumentsProcessor
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->argumentsProcessor = $argumentsProcessor;
	}

	public function getClass(): string
	{
		return 'Doctrine\ORM\Query\Expr';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return true;
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		if (in_array($methodReflection->getName(), ['countDistinct', 'isNull', 'isNotNull', 'between'], true)) {
			$args = $methodCall->getArgs();
			if (count($args) > 0) {
				$literalStringInput = true;
				foreach ($args as $arg) {
					if ($scope->getType($arg->value)->isLiteralString()->yes()) {
						continue;
					}
					$literalStringInput = false;
				}
				if ($literalStringInput) {
					return new IntersectionType([
						new StringType(),
						new AccessoryNonEmptyStringType(),
						new AccessoryLiteralStringType(),
					]);
				}
			}
		}

		$defaultReturnType = ParametersAcceptorSelector::selectSingle($methodReflection->getVariants())->getReturnType();

		$objectManager = $this->objectMetadataResolver->getObjectManager();
		if ($objectManager === null) {
			return $defaultReturnType;
		}
		$entityManagerInterface = 'Doctrine\ORM\EntityManagerInterface';
		if (!$objectManager instanceof $entityManagerInterface) {
			return $defaultReturnType;
		}

		/** @var EntityManagerInterface $objectManager */
		$objectManager = $objectManager;

		$queryBuilder = $objectManager->createQueryBuilder();

		try {
			$args = $this->argumentsProcessor->processArgs($scope, $methodReflection->getName(), $methodCall->getArgs());
		} catch (DynamicQueryBuilderArgumentException $e) {
			return $defaultReturnType;
		}

		$calledOnType = $scope->getType($methodCall->var);
		if ($calledOnType instanceof ExprType) {
			$expr = $calledOnType->getExprObject();
		} else {
			$expr = $queryBuilder->expr();
		}

		if (!method_exists($expr, $methodReflection->getName())) {
			return $defaultReturnType;
		}

		$exprValue = $expr->{$methodReflection->getName()}(...$args);
		if (is_object($exprValue)) {
			return new ExprType(get_class($exprValue), $exprValue);
		}

		return $scope->getTypeFromValue($exprValue);
	}

}
