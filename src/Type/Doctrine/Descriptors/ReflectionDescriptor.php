<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Broker\Broker;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use ReflectionClass;

class ReflectionDescriptor implements DoctrineTypeDescriptor
{

	/** @var class-string<\Doctrine\DBAL\Types\Type> */
	private $type;

	/** @var Broker */
	private $broker;

	/**
	 * @param class-string<\Doctrine\DBAL\Types\Type> $type
	 */
	public function __construct(string $type, Broker $broker)
	{
		$reflector = new ReflectionClass($type);
		$methodReflector = $reflector->getMethod('getName');
		if ($methodReflector->isStatic()) {
			$name = $type::getName();
		} else {
			$name = (new $type)->getName();
		}

		if (!\Doctrine\DBAL\Types\Type::hasType($name)) {
			\Doctrine\DBAL\Types\Type::addType($name, $type);
		}

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
		return new MixedType();
	}

}
