<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\ORM\Query\AST\Literal;
use PHPStan\Type\Constant\ConstantStringType;

class DqlConstantStringType extends ConstantStringType
{

	/** @var Literal::* */
	private $originLiteralType;

	/**
	 * @param Literal::* $originLiteralType
	 */
	public function __construct(string $value, int $originLiteralType)
	{
		parent::__construct($value, false);
		$this->originLiteralType = $originLiteralType;
	}

	/**
	 * @return Literal::*
	 */
	public function getOriginLiteralType(): int
	{
		return $this->originLiteralType;
	}

}
