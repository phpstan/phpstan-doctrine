<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type as DbalType;
use PHPStan\DependencyInjection\Container;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\Doctrine\DefaultDescriptorRegistry;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

class ReflectionDescriptor implements DoctrineTypeDescriptor, DoctrineTypeDriverAwareDescriptor
{

	/** @var class-string<DbalType> */
	private $type;

	private ReflectionProvider $reflectionProvider;

	private Container $container;

	/**
	 * @param class-string<DbalType> $type
	 */
	public function __construct(
		string $type,
		ReflectionProvider $reflectionProvider,
		Container $container
	)
	{
		$this->type = $type;
		$this->reflectionProvider = $reflectionProvider;
		$this->container = $container;
	}

	public function getType(): string
	{
		return $this->type;
	}

	public function getWritableToPropertyType(): Type
	{
		$method = $this->reflectionProvider->getClass($this->type)->getNativeMethod('convertToPHPValue');
		$type = ParametersAcceptorSelector::selectFromTypes([
			new MixedType(),
			new ObjectType(AbstractPlatform::class),
		], $method->getVariants(), false)->getReturnType();

		return TypeCombinator::removeNull($type);
	}

	public function getWritableToDatabaseType(): Type
	{
		$method = $this->reflectionProvider->getClass($this->type)->getNativeMethod('convertToDatabaseValue');
		$type = ParametersAcceptorSelector::selectFromTypes([
			new MixedType(),
			new ObjectType(AbstractPlatform::class),
		], $method->getVariants(), false)->getParameters()[0]->getType();

		return TypeCombinator::removeNull($type);
	}

	public function getDatabaseInternalType(): Type
	{
		return $this->doGetDatabaseInternalType(null);
	}

	public function getDatabaseInternalTypeForDriver(Connection $connection): Type
	{
		return $this->doGetDatabaseInternalType($connection);
	}

	private function doGetDatabaseInternalType(?Connection $connection): Type
	{
		if (!$this->reflectionProvider->hasClass($this->type)) {
			return new MixedType();
		}

		$registry = $this->container->getByType(DefaultDescriptorRegistry::class);
		$parents = $this->reflectionProvider->getClass($this->type)->getParentClassesNames();

		foreach ($parents as $dbalTypeParentClass) {
			try {
				// this assumes that if somebody inherits from DecimalType,
				// the real database type remains decimal and we can reuse its descriptor
				$descriptor = $registry->getByClassName($dbalTypeParentClass);

				return $descriptor instanceof DoctrineTypeDriverAwareDescriptor && $connection !== null
					? $descriptor->getDatabaseInternalTypeForDriver($connection)
					: $descriptor->getDatabaseInternalType();

			} catch (DescriptorNotRegisteredException $e) {
				continue;
			}
		}

		return new MixedType();
	}

}
