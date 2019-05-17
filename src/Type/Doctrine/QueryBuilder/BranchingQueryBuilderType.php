<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PHPStan\TrinaryLogic;
use PHPStan\Type\Type;

class BranchingQueryBuilderType extends QueryBuilderType
{

	public function equals(Type $type): bool
	{
		if ($type instanceof parent) {
			if (count($this->getMethodCalls()) !== count($type->getMethodCalls())) {
				return false;
			}

			foreach ($this->getMethodCalls() as $id => $methodCall) {
				if (!isset($type->getMethodCalls()[$id])) {
					return false;
				}
			}

			foreach ($type->getMethodCalls() as $id => $methodCall) {
				if (!isset($this->getMethodCalls()[$id])) {
					return false;
				}
			}

			return true;
		}

		return parent::equals($type);
	}

	public function isSuperTypeOf(Type $type): TrinaryLogic
	{
		if ($type instanceof parent) {
			return TrinaryLogic::createFromBoolean($this->equals($type));
		}

		return parent::isSuperTypeOf($type);
	}

}
