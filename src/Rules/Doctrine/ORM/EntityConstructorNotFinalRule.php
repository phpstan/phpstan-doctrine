<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\ShouldNotHappenException;
use function sprintf;

/**
 * @implements Rule<ClassMethod>
 */
class EntityConstructorNotFinalRule implements Rule
{

	public function getNodeType(): string
	{
		return ClassMethod::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if ($node->name->name !== '__construct') {
			return [];
		}

		if (!$node->isFinal()) {
			return [];
		}

		$classReflection = $scope->getClassReflection();

		if ($classReflection === null) {
			throw new ShouldNotHappenException();
		}

		return [sprintf(
			'Constructor of class %s is final which can cause problems with proxies.',
			$classReflection->getDisplayName()
		)];
	}

}
