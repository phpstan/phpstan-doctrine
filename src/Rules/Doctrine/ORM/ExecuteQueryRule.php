<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use function count;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
class ExecuteQueryRule implements Rule
{

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (!$node->name instanceof Node\Identifier) {
			return [];
		}

		if (count($node->getArgs()) === 0) {
			return [];
		}

		$methodName = $node->name->toLowerString();
		if (
			$methodName !== 'executequery'
			&& $methodName !== 'executecachequery'
		) {
			return [];
		}

		$calledOnType = $scope->getType($node->var);
		$connection = 'Doctrine\DBAL\Connection';
		if (!(new ObjectType($connection))->isSuperTypeOf($calledOnType)->yes()) {
			return [];
		}

		$queries = $scope->getType($node->getArgs()[0]->value)->getConstantStrings();
		if (count($queries) === 0) {
			return [];
		}

		foreach ($queries as $query) {
			if (!$this->isSelectQuery($query->getValue())) {
				return [
					RuleErrorBuilder::message(sprintf(
						'Only SELECT queries are allowed in the method %s. For statements, you must use executeStatement instead.',
						$node->name->toString()
					))->identifier('doctrine.query')->build(),
				];
			}
		}

		return [];
	}

	private function isSelectQuery(string $sql): bool
	{
		return str_starts_with(strtolower(trim($sql)), 'select');
	}

}
