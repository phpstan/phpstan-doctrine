<?php declare(strict_types = 1);

namespace Type\Doctrine\data\QueryResult;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\AST\Functions\DateAddFunction;
use Doctrine\ORM\Query\AST\TypedExpression;

class TypedExpressionDateAddFunction extends DateAddFunction implements TypedExpression
{

	public function getReturnType(): Type
	{
		return Type::getType(Types::DATETIME_IMMUTABLE);
	}

}
