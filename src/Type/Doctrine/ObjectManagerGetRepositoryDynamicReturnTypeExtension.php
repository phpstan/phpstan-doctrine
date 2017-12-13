<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Persistence\Mapping\MappingException;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\DoctrineClassMetadataProvider;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

class ObjectManagerGetRepositoryDynamicReturnTypeExtension implements \PHPStan\Type\DynamicMethodReturnTypeExtension
{

	/** @var string */
	private $repositoryClass;

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
		return 'Doctrine\Common\Persistence\ObjectManager';
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
			return ParametersAcceptorSelector::selectSingle(
				$methodReflection->getVariants()
			)->getReturnType();
		}
		$argType = $scope->getType($methodCall->args[0]->value);
		if (!$argType instanceof ConstantStringType) {
			return new MixedType();
		}

		return new ObjectRepositoryType($argType->getValue(), $this->repositoryClass);

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
