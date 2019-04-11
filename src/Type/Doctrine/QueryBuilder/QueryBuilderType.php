<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class QueryBuilderType extends ObjectType
{

	/** @var array<string, MethodCall> */
	private $methodCalls = [];

	/**
	 * @return array<string, MethodCall>
	 */
	public function getMethodCalls(): array
	{
		return $this->methodCalls;
	}

	public function equals(Type $type): bool
	{
		if ($type instanceof self) {
			if (count($this->methodCalls) !== count($type->methodCalls)) {
				return false;
			}

			foreach ($this->getMethodCalls() as $id => $methodCall) {
				if (!isset($type->methodCalls[$id])) {
					return false;
				}
			}

			return true;
		}

		return parent::equals($type);
	}

	public function isSuperTypeOf(Type $type): TrinaryLogic
	{
		if ($type instanceof self) {
			return TrinaryLogic::createFromBoolean($this->equals($type));
		}

		return parent::isSuperTypeOf($type);
	}

	public function append(MethodCall $methodCall): self
	{
		$object = new self($this->getClassName());
		$object->methodCalls = $this->methodCalls;
		$object->methodCalls[substr(md5(uniqid()), 0, 10)] = $methodCall;

		return $object;
	}

}
