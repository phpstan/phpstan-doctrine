<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Type\ObjectType;

class QueryBuilderType extends ObjectType
{

	/** @var MethodCall[] */
	private $methodCalls = [];

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
