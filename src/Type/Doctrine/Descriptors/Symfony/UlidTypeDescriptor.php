<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors\Symfony;

use PHPStan\Rules\Doctrine\ORM\FakeTestingSymfonyUlidType;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use Symfony\Component\Uid\Ulid;
use function in_array;
use function sprintf;

class UlidTypeDescriptor implements DoctrineTypeDescriptor
{

	private const SUPPORTED_UUID_TYPES = [
		'Symfony\Bridge\Doctrine\Types\UlidType',
		FakeTestingSymfonyUlidType::class,
	];

	private string $uuidTypeName;

	public function __construct(
		string $uuidTypeName
	)
	{
		if (!in_array($uuidTypeName, self::SUPPORTED_UUID_TYPES, true)) {
			throw new ShouldNotHappenException(sprintf(
				'Unexpected UUID column type "%s" provided',
				$uuidTypeName,
			));
		}

		$this->uuidTypeName = $uuidTypeName;
	}

	public function getType(): string
	{
		/** @var class-string<\Doctrine\DBAL\Types\Type> */
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
