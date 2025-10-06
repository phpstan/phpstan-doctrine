<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Ramsey;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Ramsey\Uuid\UuidInterface;

class UuidTypeDescriptor implements DoctrineTypeDescriptor
{

	/** @var class-string<\Doctrine\DBAL\Types\Type> */
	private string $uuidTypeName;

	/**
	 * @param class-string<\Doctrine\DBAL\Types\Type> $uuidTypeName
	 */
	public function __construct(string $uuidTypeName)
	{
		$this->uuidTypeName = $uuidTypeName;
	}

	public function getType(): string
	{
		return $this->uuidTypeName;
	}

	public function getWritableToPropertyType(): Type
	{
		return new ObjectType(UuidInterface::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(
			new StringType(),
			new ObjectType(UuidInterface::class),
		);
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
