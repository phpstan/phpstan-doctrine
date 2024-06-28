<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\ORM\AbstractQuery;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Accessory\AccessoryArrayListType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BenevolentUnionType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Doctrine\HydrationModeReturnTypeResolver;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VoidType;

final class QueryResultDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	private const METHOD_HYDRATION_MODE_ARG = [
		'getResult' => 0,
		'toIterable' => 1,
		'execute' => 1,
		'executeIgnoreQueryCache' => 1,
		'executeUsingQueryCache' => 1,
		'getOneOrNullResult' => 0,
		'getSingleResult' => 0,
	];

	private const METHOD_HYDRATION_MODE = [
		'getArrayResult' => AbstractQuery::HYDRATE_ARRAY,
	];

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var HydrationModeReturnTypeResolver */
	private $hydrationModeReturnTypeResolver;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		HydrationModeReturnTypeResolver $hydrationModeReturnTypeResolver
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->hydrationModeReturnTypeResolver = $hydrationModeReturnTypeResolver;
	}

	public function getClass(): string
	{
		return AbstractQuery::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return isset(self::METHOD_HYDRATION_MODE_ARG[$methodReflection->getName()])
			|| isset(self::METHOD_HYDRATION_MODE[$methodReflection->getName()]);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): ?Type
	{
		$methodName = $methodReflection->getName();

		if (isset(self::METHOD_HYDRATION_MODE[$methodName])) {
			$hydrationMode = new ConstantIntegerType(self::METHOD_HYDRATION_MODE[$methodName]);
		} elseif (isset(self::METHOD_HYDRATION_MODE_ARG[$methodName])) {
			$argIndex = self::METHOD_HYDRATION_MODE_ARG[$methodName];
			$args = $methodCall->getArgs();

			if (isset($args[$argIndex])) {
				$hydrationMode = $scope->getType($args[$argIndex]->value);
			} else {
				$parametersAcceptor = ParametersAcceptorSelector::selectSingle(
					$methodReflection->getVariants()
				);
				$parameter = $parametersAcceptor->getParameters()[$argIndex];
				$hydrationMode = $parameter->getDefaultValue() ?? new NullType();
			}
		} else {
			throw new ShouldNotHappenException();
		}

		$queryType = $scope->getType($methodCall->var);

		if (!$hydrationMode instanceof ConstantIntegerType) {
			return null;
		}

		return $this->hydrationModeReturnTypeResolver->getMethodReturnTypeForHydrationMode(
			$methodReflection->getName(),
			$hydrationMode->getValue(),
			$queryType->getTemplateType(AbstractQuery::class, 'TKey'),
			$queryType->getTemplateType(AbstractQuery::class, 'TResult')
		);
	}

}
