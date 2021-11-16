<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Broker\Broker;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class ReflectionDescriptor implements DoctrineTypeDescriptor
{

	/** @var class-string<\Doctrine\DBAL\Types\Type> */
	private $type;

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	/**
	 * @param class-string<\Doctrine\DBAL\Types\Type> $type
	 * @param \PHPStan\Broker\Broker $broker
	 */
	public function __construct(string $type, Broker $broker)
	{
		$this->type = $type;
		$this->broker = $broker;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getWritableToPropertyType(): Type
	{
		$type = ParametersAcceptorSelector::selectSingle($this->broker->getClass($this->type)->getNativeMethod('convertToPHPValue')->getVariants())->getReturnType();

		return TypeCombinator::removeNull($type);
	}

	public function getWritableToDatabaseType(): Type
	{
		$type = ParametersAcceptorSelector::selectSingle($this->broker->getClass($this->type)->getNativeMethod('convertToDatabaseValue')->getVariants())->getParameters()[0]->getType();

		return TypeCombinator::removeNull($type);
	}

	public function getDatabaseInternalType(): Type
	{
		return new \PHPStan\Type\MixedType();
	}

}
