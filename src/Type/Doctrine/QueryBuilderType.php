<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

class QueryBuilderType extends ObjectType
{

	/** @var MethodCall[] */
	private $methodCalls = [];

	public function __construct(string $queryBuilderClass)
	{
		parent::__construct($queryBuilderClass);
	}

	public function isSuperTypeOf(Type $type): TrinaryLogic
	{
		if ($type instanceof self) {
			if (count($this->methodCalls) > count($type->methodCalls)) {
				return TrinaryLogic::createMaybe();
			}

			return TrinaryLogic::createYes();
		}

		// todo taky musí fungovat type-specifying extension, tedy intersect
		// intersect s více metodami musí vyhrát
		// kombinovat pouze QB vycházející ze stejného řádku
		// kombinovat pouze QB typy které se nevětví - udržovat si nějaký identifikátor pokaždé?

		return parent::isSuperTypeOf($type);
	}

	/**
	 * @return MethodCall[]
	 */
	public function getMethodCalls(): array
	{
		return $this->methodCalls;
	}

	public function append(MethodCall $methodCall): self
	{
		$object = new self($this->getClassName());
		$object->methodCalls = $this->methodCalls;
		$object->methodCalls[] = $methodCall;

		return $object;
	}

}
