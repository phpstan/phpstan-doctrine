<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Type\ObjectType;

abstract class QueryBuilderType extends ObjectType
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

	public function append(MethodCall $methodCall): self
	{
		$object = new static($this->getClassName());
		$object->methodCalls = $this->methodCalls;
		$object->methodCalls[substr(md5(uniqid()), 0, 10)] = $methodCall;

		return $object;
	}

}
