<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Symfony;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Symfony\Component\Uid\Ulid;

class UlidTypeDescriptor implements DoctrineTypeDescriptor
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
		return new ObjectType(Ulid::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(
			new StringType(),
			new ObjectType(Ulid::class),
		);
	}

	public function getDatabaseInternalType(): Type
	{
		return new StringType();
	}

}
