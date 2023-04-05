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
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
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
use PHPStan\Type\TypeWithClassName;
use PHPStan\Type\VoidType;
use function count;

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
		'getScalarResult' => AbstractQuery::HYDRATE_SCALAR,
		'getSingleColumnResult' => AbstractQuery::HYDRATE_SCALAR_COLUMN,
		'getSingleScalarResult' => AbstractQuery::HYDRATE_SINGLE_SCALAR,
	];

	/** @var ObjectMetadataResolver */
	private $objectMetadataResolver;

	/** @var DescriptorRegistry */
	private $descriptorRegistry;

	public function __construct(
		ObjectMetadataResolver $objectMetadataResolver,
		DescriptorRegistry $descriptorRegistry
	)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
		$this->descriptorRegistry = $descriptorRegistry;
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
	): Type
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

		return $this->getMethodReturnTypeForHydrationMode(
			$methodReflection,
			$hydrationMode,
			$queryType->getTemplateType(AbstractQuery::class, 'TKey'),
			$queryType->getTemplateType(AbstractQuery::class, 'TResult')
		);
	}

	private function getMethodReturnTypeForHydrationMode(
		MethodReflection $methodReflection,
		Type $hydrationMode,
		Type $queryKeyType,
		Type $queryResultType
	): Type
	{
		$isVoidType = (new VoidType())->isSuperTypeOf($queryResultType);

		if ($isVoidType->yes()) {
			// A void query result type indicates an UPDATE or DELETE query.
			// In this case all methods return the number of affected rows.
			return new IntegerType();
		}

		if ($isVoidType->maybe()) {
			// We can't be sure what the query type is, so we return the
			// declared return type of the method.
			return $this->originalReturnType($methodReflection);
		}

		if (!$hydrationMode instanceof ConstantIntegerType) {
			return $this->originalReturnType($methodReflection);
		}

		$singleResult = false;
		switch ($hydrationMode->getValue()) {
			case AbstractQuery::HYDRATE_OBJECT:
				break;
			case AbstractQuery::HYDRATE_ARRAY:
				$queryResultType = $this->getArrayHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SCALAR:
				$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SINGLE_SCALAR:
				$singleResult = true;
				$queryResultType = $this->getSingleScalarHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SIMPLEOBJECT:
				$queryResultType = $this->getSimpleObjectHydratedReturnType($queryResultType);
				break;
			case AbstractQuery::HYDRATE_SCALAR_COLUMN:
				$queryResultType = $this->getScalarColumnHydratedReturnType($queryResultType);
				break;
			default:
				return $this->originalReturnType($methodReflection);
		}

		switch ($methodReflection->getName()) {
			case 'getSingleResult':
				return $queryResultType;
			case 'getOneOrNullResult':
				return TypeCombinator::addNull($queryResultType);
			case 'toIterable':
				return new IterableType(
					$queryKeyType->isNull()->yes() ? new IntegerType() : $queryKeyType,
					$queryResultType
				);
			default:
				if ($singleResult) {
					return $queryResultType;
				}

				if ($queryKeyType->isNull()->yes()) {
					return AccessoryArrayListType::intersectWith(new ArrayType(
						new IntegerType(),
						$queryResultType
					));
				}
				return new ArrayType(
					$queryKeyType,
					$queryResultType
				);
		}
	}

	private function getArrayHydratedReturnType(Type $queryResultType): Type
	{
		$objectManager = $this->objectMetadataResolver->getObjectManager();
		$descriptorRegistry = $this->descriptorRegistry;

		return TypeTraverser::map(
			$queryResultType,
			static function (Type $type, callable $traverse) use ($objectManager, $descriptorRegistry): Type {
				$isObject = (new ObjectWithoutClassType())->isSuperTypeOf($type);
				if ($isObject->no()) {
					return $traverse($type);
				}
				if (
					$isObject->maybe()
					|| !$type instanceof TypeWithClassName
					|| $objectManager === null
				) {
					return new MixedType();
				}

				if (!$objectManager->getMetadataFactory()->hasMetadataFor($type->getClassName())) {
					return $traverse($type);
				}

				$metadata = $objectManager->getMetadataFactory()->getMetadataFor($type->getClassName());

				$types = [];
				$keys = [];
				foreach ($metadata->fieldMappings as $fieldMapping) {
					try {
						$type = $descriptorRegistry->get($fieldMapping['type'])->getWritableToPropertyType();
					} catch (DescriptorNotRegisteredException $exception) {
						return new ArrayType(new MixedType(), new MixedType());
					}

					$nullable = isset($fieldMapping['nullable'])
						? $fieldMapping['nullable'] === true
						: false;
					if ($nullable) {
						$type = TypeCombinator::addNull($type);
					}

					$types[] = $type;
					$keys[] = new ConstantStringType($fieldMapping['fieldName']);
				}

				return new ConstantArrayType($keys, $types);
			}
		);
	}

	private function getScalarHydratedReturnType(Type $queryResultType): Type
	{
		if (!$queryResultType->isArray()->yes()) {
			return new ArrayType(new MixedType(), new MixedType());
		}

		foreach ($queryResultType->getArrays() as $arrayType) {
			$itemType = $arrayType->getItemType();

			if (
				!(new ObjectWithoutClassType())->isSuperTypeOf($itemType)->no()
				|| !$itemType->isArray()->no()
			) {
				return new ArrayType(new MixedType(), new MixedType());
			}
		}

		return $queryResultType;
	}

	private function getSimpleObjectHydratedReturnType(Type $queryResultType): Type
	{
		if ((new ObjectWithoutClassType())->isSuperTypeOf($queryResultType)->yes()) {
			return $queryResultType;
		}

		return new MixedType();
	}

	private function getSingleScalarHydratedReturnType(Type $queryResultType): Type
	{
		$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
		if (!$queryResultType->isConstantArray()->yes()) {
			return new MixedType();
		}

		$types = [];
		foreach ($queryResultType->getConstantArrays() as $constantArrayType) {
			$values = $constantArrayType->getValueTypes();
			if (count($values) !== 1) {
				return new MixedType();
			}

			$types[] = $constantArrayType->getFirstIterableValueType();
		}

		return TypeCombinator::union(...$types);
	}

	private function getScalarColumnHydratedReturnType(Type $queryResultType): Type
	{
		$queryResultType = $this->getScalarHydratedReturnType($queryResultType);
		if (!$queryResultType->isConstantArray()->yes()) {
			return new MixedType();
		}

		$types = [];
		foreach ($queryResultType->getConstantArrays() as $constantArrayType) {
			$values = $constantArrayType->getValueTypes();
			if (count($values) !== 1) {
				return new MixedType();
			}

			$types[] = $constantArrayType->getFirstIterableValueType();
		}

		return TypeCombinator::union(...$types);
	}

	private function originalReturnType(MethodReflection $methodReflection): Type
	{
		$parametersAcceptor = ParametersAcceptorSelector::selectSingle(
			$methodReflection->getVariants()
		);

		return $parametersAcceptor->getReturnType();
	}

}
