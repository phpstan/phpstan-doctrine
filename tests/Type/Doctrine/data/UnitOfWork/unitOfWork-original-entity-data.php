<?php declare(strict_types = 1);

namespace UnitOfWorkOriginalEntityData;

use Doctrine\ORM\UnitOfWork;
use QueryResult\Entities\Many;
use QueryResult\Entities\One;
use QueryResult\Entities\Simple;
use function PHPStan\Testing\assertType;

final class UnitOfWorkOriginalEntityDataAssertions
{
	public function simple(UnitOfWork $unitOfWork, Simple $entity): void
	{
		assertType(
			'array{id: lowercase-string&numeric-string&uppercase-string, intColumn: int, floatColumn: float, decimalColumn: numeric-string&uppercase-string, stringColumn: string, stringNullColumn: string|null, mixedColumn: mixed}',
			$unitOfWork->getOriginalEntityData($entity)
		);
	}

	public function associations(UnitOfWork $unitOfWork, Many $entity): void
	{
		assertType(
			'array{id: lowercase-string&numeric-string&uppercase-string, intColumn: int, stringColumn: string, stringNullColumn: string|null, datetimeColumn: DateTime, datetimeImmutableColumn: DateTimeImmutable, simpleArrayColumn: list<string>, one: QueryResult\\Entities\\One, oneNull: QueryResult\\Entities\\One|null, oneDefaultNullability: QueryResult\\Entities\\One|null, compoundPk: QueryResult\\Entities\\CompoundPk|null, compoundPkAssoc: QueryResult\\Entities\\CompoundPkAssoc|null}',
			$unitOfWork->getOriginalEntityData($entity)
		);
	}

	public function persistentCollection(UnitOfWork $unitOfWork, One $entity): void
	{
		$originalData = $unitOfWork->getOriginalEntityData($entity);
		assertType('Doctrine\\ORM\\PersistentCollection', $originalData['manies']);
	}

	public function unknownEntity(UnitOfWork $unitOfWork, object $entity): void
	{
		assertType(
			'array<string, mixed>',
			$unitOfWork->getOriginalEntityData($entity)
		);
	}
}
