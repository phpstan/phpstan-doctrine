<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use Doctrine\Common\Persistence\ObjectRepository;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\GenericTypeVariableResolver;
use PHPStan\Type\Type;
use PHPStan\Type\TypeWithClassName;

class EntityRepositoryCreateQueryBuilderDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{

	public function getClass(): string
	{
		return 'Doctrine\ORM\EntityRepository';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'createQueryBuilder';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		$calledOnType = $scope->getType($methodCall->var);
		$entityName = $this->extractEntityName($calledOnType);
		$entityNameArg = new Arg($entityName === null ? new Variable('entityName') : new String_($entityName));
		$fromArgs = $methodCall->args;
		array_unshift($fromArgs, $entityNameArg);

		$callStack = new MethodCall($methodCall->var, new Identifier('getEntityManager'));
		$callStack = new MethodCall($callStack, new Identifier('createQueryBuilder'));
		$callStack = new MethodCall($callStack, new Identifier('select'), [$methodCall->args[0]]);
		$callStack = new MethodCall($callStack, new Identifier('from'), $fromArgs);

		return $scope->getType($callStack);
	}

	private function extractEntityName(Type $repositoryType): ?string
	{
		if (! $repositoryType instanceof TypeWithClassName) {
			return null;
		}

		$entityClassType = GenericTypeVariableResolver::getType($repositoryType, ObjectRepository::class, 'TEntityClass');
		if (! $entityClassType instanceof TypeWithClassName) {
			return null;
		}

		return $entityClassType->getClassName();
	}

}
