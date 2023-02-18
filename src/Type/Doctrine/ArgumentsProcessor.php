<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Arg;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\Doctrine\QueryBuilder\Expr\ExprType;
use function count;
use function strpos;

/** @api */
class ArgumentsProcessor
{

	/**
	 * @param Arg[] $methodCallArgs
	 * @return mixed[]
	 * @throws DynamicQueryBuilderArgumentException
	 */
	public function processArgs(
		Scope $scope,
		string $methodName,
		array $methodCallArgs
	): array
	{
		$args = [];
		foreach ($methodCallArgs as $arg) {
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
					if (!$valueType instanceof ConstantScalarType) {
						throw new DynamicQueryBuilderArgumentException();
					}
					$array[$keyType->getValue()] = $valueType->getValue();
				}

				$args[] = $array;
				continue;
			}
			if (!$value instanceof ConstantScalarType) {
				throw new DynamicQueryBuilderArgumentException();
			}

			$args[] = $value->getValue();
		}

		return $args;
	}

}
