<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use function is_array;

class QueryAggregateFunctionDetectorTreeWalker extends Query\TreeWalkerAdapter
{

	public const HINT_HAS_AGGREGATE_FUNCTION = self::class . '::HINT_HAS_AGGREGATE_FUNCTION';

	public function walkSelectStatement(AST\SelectStatement $selectStatement): void
	{
		$this->walkNode($selectStatement->selectClause);
	}

	/**
	 * @param mixed $node
	 */
	public function walkNode($node): void
	{
		if (!$node instanceof AST\Node) {
			return;
		}

		if ($node instanceof AST\Subselect) {
			return;
		}

		if ($this->isAggregateFunction($node)) {
			$this->markAggregateFunctionFound();
			return;
		}

		foreach ((array) $node as $property) {
			if ($property instanceof AST\Node) {
				$this->walkNode($property);
			}

			if (is_array($property)) {
				foreach ($property as $propertyValue) {
					$this->walkNode($propertyValue);
				}
			}

			if ($this->wasAggregateFunctionFound()) {
				return;
			}
		}
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

	private function wasAggregateFunctionFound(): bool
	{
		return $this->_getQuery()->hasHint(self::HINT_HAS_AGGREGATE_FUNCTION);
	}

}
