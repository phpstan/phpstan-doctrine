<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder\Expr;

use PHPStan\Type\ObjectType;

/** @api */
class ExprType extends ObjectType
{

	private object $exprObject;

	/**
	 * @param object $exprObject
	 */
	public function __construct(string $className, $exprObject)
	{
		parent::__construct($className);
		$this->exprObject = $exprObject;
	}

	/**
	 * @return object
	 */
	public function getExprObject()
	{
		return $this->exprObject;
	}

}
