<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use function is_string;

class QueryAggregateFunctionDetectorTreeWalker extends Query\TreeWalkerAdapter
{

	public const HINT_HAS_AGGREGATE_FUNCTION = self::class . '::HINT_HAS_AGGREGATE_FUNCTION';

	public function walkSelectStatement(AST\SelectStatement $selectStatement): void
	{
		$this->doWalkSelectClause($selectStatement->selectClause);
	}

	/**
	 * @param AST\SelectClause $selectClause
	 */
	public function doWalkSelectClause($selectClause): void
	{
		foreach ($selectClause->selectExpressions as $selectExpression) {
			$this->doWalkSelectExpression($selectExpression);
		}
	}

	/**
	 * @param AST\SelectExpression $selectExpression
	 */
	public function doWalkSelectExpression($selectExpression): void
	{
		$this->doWalkNode($selectExpression->expression);
	}

	/**
	 * @param mixed $expr
	 */
	private function doWalkNode($expr): void
	{
		if ($expr instanceof AST\AggregateExpression) {
			$this->markAggregateFunctionFound();

		} elseif ($expr instanceof AST\Functions\FunctionNode) {
			if ($this->isAggregateFunction($expr)) {
				$this->markAggregateFunctionFound();
			}

		} elseif ($expr instanceof AST\SimpleArithmeticExpression) {
			foreach ($expr->arithmeticTerms as $term) {
				$this->doWalkArithmeticTerm($term);
			}

		} elseif ($expr instanceof AST\ArithmeticTerm) {
			$this->doWalkArithmeticTerm($expr);

		} elseif ($expr instanceof AST\ArithmeticFactor) {
			$this->doWalkArithmeticFactor($expr);

		} elseif ($expr instanceof AST\ParenthesisExpression) {
			$this->doWalkArithmeticPrimary($expr->expression);

		} elseif ($expr instanceof AST\NullIfExpression) {
			$this->doWalkNullIfExpression($expr);

		} elseif ($expr instanceof AST\CoalesceExpression) {
			$this->doWalkCoalesceExpression($expr);

		} elseif ($expr instanceof AST\GeneralCaseExpression) {
			$this->doWalkGeneralCaseExpression($expr);

		} elseif ($expr instanceof AST\SimpleCaseExpression) {
			$this->doWalkSimpleCaseExpression($expr);

		} elseif ($expr instanceof AST\ArithmeticExpression) {
			$this->doWalkArithmeticExpression($expr);

		} elseif ($expr instanceof AST\ComparisonExpression) {
			$this->doWalkComparisonExpression($expr);

		} elseif ($expr instanceof AST\BetweenExpression) {
			$this->doWalkBetweenExpression($expr);
		}
	}

	public function doWalkCoalesceExpression(AST\CoalesceExpression $coalesceExpression): void
	{
		foreach ($coalesceExpression->scalarExpressions as $scalarExpression) {
			$this->doWalkSimpleArithmeticExpression($scalarExpression);
		}
	}

	public function doWalkNullIfExpression(AST\NullIfExpression $nullIfExpression): void
	{
		if (!is_string($nullIfExpression->firstExpression)) {
			$this->doWalkSimpleArithmeticExpression($nullIfExpression->firstExpression);
		}

		if (is_string($nullIfExpression->secondExpression)) {
			return;
		}

		$this->doWalkSimpleArithmeticExpression($nullIfExpression->secondExpression);
	}

	public function doWalkGeneralCaseExpression(AST\GeneralCaseExpression $generalCaseExpression): void
	{
		foreach ($generalCaseExpression->whenClauses as $whenClause) {
			$this->doWalkConditionalExpression($whenClause->caseConditionExpression);
			$this->doWalkSimpleArithmeticExpression($whenClause->thenScalarExpression);
		}

		$this->doWalkSimpleArithmeticExpression($generalCaseExpression->elseScalarExpression);
	}

	public function doWalkSimpleCaseExpression(AST\SimpleCaseExpression $simpleCaseExpression): void
	{
		foreach ($simpleCaseExpression->simpleWhenClauses as $simpleWhenClause) {
			$this->doWalkSimpleArithmeticExpression($simpleWhenClause->caseScalarExpression);
			$this->doWalkSimpleArithmeticExpression($simpleWhenClause->thenScalarExpression);
		}

		$this->doWalkSimpleArithmeticExpression($simpleCaseExpression->elseScalarExpression);
	}

	/**
	 * @param AST\ConditionalExpression|AST\Phase2OptimizableConditional $condExpr
	 */
	public function doWalkConditionalExpression($condExpr): void
	{
		if (!$condExpr instanceof AST\ConditionalExpression) {
			$this->doWalkConditionalTerm($condExpr); // @phpstan-ignore-line PHPStan do not read @psalm-inheritors of Phase2OptimizableConditional
			return;
		}

		foreach ($condExpr->conditionalTerms as $conditionalTerm) {
			$this->doWalkConditionalTerm($conditionalTerm);
		}
	}

	/**
	 * @param AST\ConditionalTerm|AST\ConditionalPrimary|AST\ConditionalFactor $condTerm
	 */
	public function doWalkConditionalTerm($condTerm): void
	{
		if (!$condTerm instanceof AST\ConditionalTerm) {
			$this->doWalkConditionalFactor($condTerm);
			return;
		}

		foreach ($condTerm->conditionalFactors as $conditionalFactor) {
			$this->doWalkConditionalFactor($conditionalFactor);
		}
	}

	/**
	 * @param AST\ConditionalFactor|AST\ConditionalPrimary $factor
	 */
	public function doWalkConditionalFactor($factor): void
	{
		if (!$factor instanceof AST\ConditionalFactor) {
			$this->doWalkConditionalPrimary($factor);
		} else {
			$this->doWalkConditionalPrimary($factor->conditionalPrimary);
		}
	}

	/**
	 * @param AST\ConditionalPrimary $primary
	 */
	public function doWalkConditionalPrimary($primary): void
	{
		if ($primary->isSimpleConditionalExpression()) {
			if ($primary->simpleConditionalExpression instanceof AST\ComparisonExpression) {
				$this->doWalkComparisonExpression($primary->simpleConditionalExpression);
				return;
			}
			$this->doWalkNode($primary->simpleConditionalExpression);
		}

		if (!$primary->isConditionalExpression()) {
			return;
		}

		if ($primary->conditionalExpression === null) {
			return;
		}

		$this->doWalkConditionalExpression($primary->conditionalExpression);
	}

	/**
	 * @param AST\BetweenExpression $betweenExpr
	 */
	public function doWalkBetweenExpression($betweenExpr): void
	{
		$this->doWalkArithmeticExpression($betweenExpr->expression);
		$this->doWalkArithmeticExpression($betweenExpr->leftBetweenExpression);
		$this->doWalkArithmeticExpression($betweenExpr->rightBetweenExpression);
	}

	/**
	 * @param AST\ComparisonExpression $compExpr
	 */
	public function doWalkComparisonExpression($compExpr): void
	{
		$leftExpr = $compExpr->leftExpression;
		$rightExpr = $compExpr->rightExpression;

		if ($leftExpr instanceof AST\Node) {
			$this->doWalkNode($leftExpr);
		}

		if (!($rightExpr instanceof AST\Node)) {
			return;
		}

		$this->doWalkNode($rightExpr);
	}

	/**
	 * @param AST\ArithmeticExpression $arithmeticExpr
	 */
	public function doWalkArithmeticExpression($arithmeticExpr): void
	{
		if (!$arithmeticExpr->isSimpleArithmeticExpression()) {
			return;
		}

		if ($arithmeticExpr->simpleArithmeticExpression === null) {
			return;
		}

		$this->doWalkSimpleArithmeticExpression($arithmeticExpr->simpleArithmeticExpression);
	}

	/**
	 * @param AST\Node|string $simpleArithmeticExpr
	 */
	public function doWalkSimpleArithmeticExpression($simpleArithmeticExpr): void
	{
		if (!$simpleArithmeticExpr instanceof AST\SimpleArithmeticExpression) {
			$this->doWalkArithmeticTerm($simpleArithmeticExpr);
			return;
		}

		foreach ($simpleArithmeticExpr->arithmeticTerms as $term) {
			$this->doWalkArithmeticTerm($term);
		}
	}

	/**
	 * @param AST\Node|string $term
	 */
	public function doWalkArithmeticTerm($term): void
	{
		if (is_string($term)) {
			return;
		}

		if (!$term instanceof AST\ArithmeticTerm) {
			$this->doWalkArithmeticFactor($term);
			return;
		}

		foreach ($term->arithmeticFactors as $factor) {
			$this->doWalkArithmeticFactor($factor);
		}
	}

	/**
	 * @param AST\Node|string $factor
	 */
	public function doWalkArithmeticFactor($factor): void
	{
		if (is_string($factor)) {
			return;
		}

		if (!$factor instanceof AST\ArithmeticFactor) {
			$this->doWalkArithmeticPrimary($factor);
			return;
		}

		$this->doWalkArithmeticPrimary($factor->arithmeticPrimary);
	}

	/**
	 * @param AST\Node|string $primary
	 */
	public function doWalkArithmeticPrimary($primary): void
	{
		if ($primary instanceof AST\SimpleArithmeticExpression) {
			$this->doWalkSimpleArithmeticExpression($primary);
			return;
		}

		if (!($primary instanceof AST\Node)) {
			return;
		}

		$this->doWalkNode($primary);
	}

	private function isAggregateFunction(AST\Node $node): bool
	{
		return $node instanceof AST\Functions\AvgFunction
			|| $node instanceof AST\Functions\CountFunction
			|| $node instanceof AST\Functions\MaxFunction
			|| $node instanceof AST\Functions\MinFunction
			|| $node instanceof AST\Functions\SumFunction
			|| $node instanceof AST\AggregateExpression;
	}

	private function markAggregateFunctionFound(): void
	{
		$this->_getQuery()->setHint(self::HINT_HAS_AGGREGATE_FUNCTION, true);
	}

}
