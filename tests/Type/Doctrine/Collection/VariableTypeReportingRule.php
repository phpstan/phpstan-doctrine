<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Collection;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Type\VerbosityLevel;
use function is_string;
use function sprintf;

/**
 * @implements Rule<Node\Expr\Variable>
 */
class VariableTypeReportingRule implements Rule
{

	public function getNodeType(): string
	{
		return Node\Expr\Variable::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!is_string($node->name)) {
			return [];
		}
		if (!$scope->isInFirstLevelStatement()) {
			return [];
		}

		if ($scope->isInExpressionAssign($node)) {
			return [];
		}

		return [
			sprintf(
				'Variable $%s is: %s',
				$node->name,
				$scope->getType($node)->describe(VerbosityLevel::value())
			),
		];
	}

}
