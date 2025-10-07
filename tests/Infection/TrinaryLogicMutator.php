<?php declare(strict_types = 1);

namespace PHPStan\Infection;

use Infection\Mutator\Definition;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use LogicException;
use PhpParser\Node;
use function in_array;

/**
 * @implements Mutator<Node\Expr\MethodCall>
 */
final class TrinaryLogicMutator implements Mutator
{

	public static function getDefinition(): Definition
	{
		return new Definition(
			<<<'TXT'
				Replaces TrinaryLogic->yes() with !TrinaryLogic->no() and vice versa.
				TXT
			,
			MutatorCategory::ORTHOGONAL_REPLACEMENT,
			null,
			<<<'DIFF'
				- $type->isBoolean()->yes();
				+ !$type->isBoolean()->no();
			DIFF,
		);
	}

	public function getName(): string
	{
		return 'TrinaryLogicMutator';
	}

	public function canMutate(Node $node): bool
	{
		if (!$node instanceof Node\Expr\MethodCall) {
			return false;
		}

		if (!$node->name instanceof Node\Identifier) {
			return false;
		}

		if (!in_array($node->name->name, ['yes', 'no'], true)) {
			return false;
		}

		return true;
	}

	public function mutate(Node $node): iterable
	{
		if (!$node->name instanceof Node\Identifier) {
			throw new LogicException();
		}

		if ($node->name->name === 'yes') {
			yield new Node\Expr\BooleanNot(new Node\Expr\MethodCall($node->var, 'no'));
		} else {
			yield new Node\Expr\BooleanNot(new Node\Expr\MethodCall($node->var, 'yes'));
		}
	}

}
