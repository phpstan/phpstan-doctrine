<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\DoctrineClassMetadataProvider;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Type;

class EntityManagerGetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

    /**
     * @var DoctrineClassMetadataProvider
     */
    private $metadataProvider;

    public function __construct(DoctrineClassMetadataProvider $metadataProvider)
	{
        $this->metadataProvider = $metadataProvider;
        AnnotationRegistry::registerLoader('class_exists');
    }

	public function getClass(): string
	{
		return \Doctrine\Common\Persistence\ObjectManager::class;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getRepository';
	}

	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope
	): Type
	{
		if (count($methodCall->args) === 0) {
			return $methodReflection->getReturnType();
		}
		$arg = $methodCall->args[0]->value;
		if (!($arg instanceof \PhpParser\Node\Expr\ClassConstFetch)) {
			return $methodReflection->getReturnType();
		}

		$class = $arg->class;
		if (!($class instanceof \PhpParser\Node\Name)) {
			return $methodReflection->getReturnType();
		}

		$class = (string) $class;
		if ($class === 'static') {
			return $methodReflection->getReturnType();
		}

		if ($class === 'self') {
			$class = $scope->getClassReflection()->getName();
		}

        try {
            $metadata = $this->metadataProvider->getMetadataFor($class);
            return new EntityRepositoryType($class, $metadata->customRepositoryClassName ?: $this->metadataProvider->getBaseRepositoryClass());
        } catch (MappingException $e) {
		    return new EntityRepositoryType($class, $this->metadataProvider->getBaseRepositoryClass());
        }
	}

}
