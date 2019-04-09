<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\Doctrine\ORM\DynamicQueryBuilderArgumentException;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\Doctrine\QueryBuilder\Expr\ExprType;

class ArgumentsProcessor
{

	/**
	 * @param \PHPStan\Analyser\Scope $scope
	 * @param string $methodName
	 * @param \PhpParser\Node\Arg[] $methodCallArgs
	 * @return mixed[]
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
			// todo $qb->expr() support
			if ($value instanceof ConstantArrayType) {
				$array = [];
				foreach ($value->getKeyTypes() as $i => $keyType) {
					$valueType = $value->getValueTypes()[$i];
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
