<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use PHPStan\Type\ObjectType;

class ExprType extends ObjectType
{

	/** @var \PhpParser\Node\Arg[] */
	private $constructorArgs;

	/**
	 * @param string $className
	 * @param \PhpParser\Node\Arg[] $constructorArgs
	 */
	public function __construct(string $className, array $constructorArgs)
	{
		parent::__construct($className);
		$this->constructorArgs = $constructorArgs;
	}

	/**
	 * @return \PhpParser\Node\Arg[]
	 */
	public function getConstructorArgs(): array
	{
		return $this->constructorArgs;
	}

}
