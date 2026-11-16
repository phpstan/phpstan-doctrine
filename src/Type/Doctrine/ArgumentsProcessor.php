<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Doctrine\QueryBuilder\Expr\ExprType;
use function array_slice;
use function count;
use function in_array;
use function strpos;
use function strtolower;

/** @api */
class ArgumentsProcessor
{

	private ObjectMetadataResolver $objectMetadataResolver;

	public function __construct(ObjectMetadataResolver $objectMetadataResolver)
	{
		$this->objectMetadataResolver = $objectMetadataResolver;
	}

	/**
	 * @param Arg[] $methodCallArgs
	 * @return list<mixed>
	 * @throws DynamicQueryBuilderArgumentException
	 */
	public function processArgs(
		Scope $scope,
		string $methodName,
		array $methodCallArgs
	): array
	{
		$args = [];
		foreach ($methodCallArgs as $argIndex => $arg) {
			if ($arg->unpack) {
				throw new DynamicQueryBuilderArgumentException();
			}
			$value = $scope->getType($arg->value);
			if (
				$value instanceof ExprType
				&& strpos($value->getClassName(), 'Doctrine\ORM\Query\Expr') === 0
			) {
				$args[] = $value->getExprObject();
				continue;
			}
			if (count($value->getConstantArrays()) === 1) {
				$array = [];
				foreach ($value->getConstantArrays()[0]->getKeyTypes() as $i => $keyType) {
					$valueType = $value->getConstantArrays()[0]->getValueTypes()[$i];
					if (count($valueType->getConstantScalarValues()) !== 1) {
						throw new DynamicQueryBuilderArgumentException();
					}
					$array[$keyType->getValue()] = $valueType->getConstantScalarValues()[0];
				}

				$args[] = $array;
				continue;
			}

			if ($value->isClassString()->yes() && count($value->getClassStringObjectType()->getObjectClassNames()) === 1) {
				/** @var class-string $className */
				$className = $value->getClassStringObjectType()->getObjectClassNames()[0];
				$isEntityClassArgument = $argIndex === 0 && $this->isEntityClassMethod($methodName);
				if ($isEntityClassArgument) {
					if ($this->objectMetadataResolver->isTransient($className)) {
						throw new DynamicQueryBuilderArgumentException();
					}
					$args[] = $className;
					continue;
				}
			}

			if (count($value->getConstantScalarValues()) !== 1) {
				throw new DynamicQueryBuilderArgumentException();
			}

			$args[] = $value->getConstantScalarValues()[0];
		}

		return $args;
	}

	/**
	 * @param Arg[] $methodCallArgs
	 * @return list<list<mixed>>
	 * @throws DynamicQueryBuilderArgumentException
	 */
	public function processQueryBuilderArgsAlternatives(
		Scope $scope,
		string $methodName,
		array $methodCallArgs
	): array
	{
		if (isset($methodCallArgs[0]) && !$methodCallArgs[0]->unpack && $this->isEntityClassMethod($methodName)) {
			$expr = $methodCallArgs[0]->value;
			if (
				$expr instanceof ClassConstFetch
				&& $expr->name instanceof Identifier
				&& $expr->name->toLowerString() === 'class'
				&& $expr->class instanceof Expr
				&& count($scope->getType($expr)->getConstantScalarValues()) === 0
			) {
				$classNames = $this->objectMetadataResolver->getConcreteEntityClasses($scope->getType($expr->class));
				if ($classNames !== null) {
					$remainingArgs = $this->processArgs($scope, $methodName, array_slice($methodCallArgs, 1, null, true));
					$alternatives = [];
					foreach ($classNames as $className) {
						$alternatives[] = [$className, ...$remainingArgs];
					}

					return $alternatives;
				}
			}
		}

		return [$this->processArgs($scope, $methodName, $methodCallArgs)];
	}

	private function isEntityClassMethod(string $methodName): bool
	{
		return in_array(strtolower($methodName), ['from', 'join', 'innerjoin', 'leftjoin'], true);
	}

}
