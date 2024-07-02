<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\ORM\AbstractQuery;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Doctrine\HydrationModeReturnTypeResolver;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\Type;

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
		return isset(self::METHOD_HYDRATION_MODE_ARG[$methodReflection->getName()]);
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): ?Type
	{
		$methodName = $methodReflection->getName();

		if (!isset(self::METHOD_HYDRATION_MODE_ARG[$methodName])) {
			throw new ShouldNotHappenException();
		}

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

		$queryType = $scope->getType($methodCall->var);

		return $this->hydrationModeReturnTypeResolver->getMethodReturnTypeForHydrationMode(
			$methodReflection->getName(),
			$hydrationMode,
			$queryType->getTemplateType(AbstractQuery::class, 'TKey'),
			$queryType->getTemplateType(AbstractQuery::class, 'TResult'),
			$this->objectMetadataResolver->getObjectManager()
		);
	}

}
