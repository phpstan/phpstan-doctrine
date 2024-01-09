<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use function md5;
use function substr;
use function uniqid;

/** @api */
abstract class QueryBuilderType extends ObjectType
{

	/** @var array<string, MethodCall> */
	private $methodCalls = [];

	/** @var Scope */
	private $scope;

	final public function __construct(
		string $className,
		Scope $scope,
		?Type $subtractedType = null
	)
	{
		parent::__construct($className, $subtractedType);
		$this->scope = $scope;
	}

	final public function getScope(): Scope
	{
		return $this->scope;
	}

	/**
	 * @return array<string, MethodCall>
	 */
	public function getMethodCalls(): array
	{
		return $this->methodCalls;
	}

	public function append(MethodCall $methodCall): self
	{
		$object = new static($this->getClassName(), $this->getScope());
		$object->methodCalls = $this->methodCalls;
		$object->methodCalls[substr(md5(uniqid()), 0, 10)] = $methodCall;

		return $object;
	}

}
