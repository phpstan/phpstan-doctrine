<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class ReflectionDescriptor implements DoctrineTypeDescriptor
{

	/** @var class-string<\Doctrine\DBAL\Types\Type> */
	private $type;

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/**
	 * @param class-string<\Doctrine\DBAL\Types\Type> $type
	 */
	public function __construct(string $type, ReflectionProvider $reflectionProvider)
	{
		$this->type = $type;
		$this->reflectionProvider = $reflectionProvider;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getWritableToPropertyType(): Type
	{
		$type = ParametersAcceptorSelector::selectSingle($this->reflectionProvider->getClass($this->type)->getNativeMethod('convertToPHPValue')->getVariants())->getReturnType();

		return TypeCombinator::removeNull($type);
	}

	public function getWritableToDatabaseType(): Type
	{
		$type = ParametersAcceptorSelector::selectSingle($this->reflectionProvider->getClass($this->type)->getNativeMethod('convertToDatabaseValue')->getVariants())->getParameters()[0]->getType();

		return TypeCombinator::removeNull($type);
	}

	public function getDatabaseInternalType(): Type
	{
		return new MixedType();
	}

}
