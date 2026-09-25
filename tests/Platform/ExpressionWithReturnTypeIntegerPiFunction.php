<?php declare(strict_types = 1);

namespace PHPStan\Platform;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\AST\ExpressionWithReturnType;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class ExpressionWithReturnTypeIntegerPiFunction extends FunctionNode implements ExpressionWithReturnType
{

	public function getSql(SqlWalker $sqlWalker): string
	{
		return '3.14159';
	}

	public function parse(Parser $parser): void
	{
		$parser->match(TokenType::T_IDENTIFIER);
		$parser->match(TokenType::T_OPEN_PARENTHESIS);
		$parser->match(TokenType::T_CLOSE_PARENTHESIS);
	}

	public function getReturnTypeName(): string
	{
		return Types::INTEGER;
	}

}
