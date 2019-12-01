<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Ramsey;

use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Ramsey\Uuid\UuidInterface;

class UuidTypeDescriptor implements DoctrineTypeDescriptor
{

	private const SUPPORTED_UUID_TYPES = [
		\Ramsey\Uuid\Doctrine\UuidType::class,
		\Ramsey\Uuid\Doctrine\UuidBinaryType::class,
		\Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType::class,
	];

	/**
	 * @phpstan-var class-string<\Doctrine\DBAL\Types\Type>
	 * @var string
	 */
	private $uuidTypeName;

	public function __construct(
		string $uuidTypeName
	)
	{
		if (!in_array($uuidTypeName, self::SUPPORTED_UUID_TYPES, true)) {
			throw new \PHPStan\ShouldNotHappenException(sprintf(
				'Unexpected UUID column type "%s" provided',
				$uuidTypeName
			));
		}

		$this->uuidTypeName = $uuidTypeName;
	}

	public function getType(): string
	{
		return $this->uuidTypeName;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\ObjectType(UuidInterface::class);
	}

	public function getWritableToDatabaseType(): Type
	{
		return TypeCombinator::union(
			new \PHPStan\Type\StringType(),
			new \PHPStan\Type\ObjectType(UuidInterface::class)
		);
	}

}
