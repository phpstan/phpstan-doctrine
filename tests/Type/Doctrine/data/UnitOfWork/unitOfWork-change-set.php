<?php declare(strict_types = 1);

namespace UnitOfWorkChangeSet;

use Doctrine\ORM\UnitOfWork;
use QueryResult\Entities\Many;
use QueryResult\Entities\One;
use QueryResult\Entities\Simple;
use function PHPStan\Testing\assertType;

final class UnitOfWorkChangeSetAssertions
{
	public function simpleField(UnitOfWork $unitOfWork, Simple $entity): void
	{
		assertType(
			'array{intColumn: array{int, int}, floatColumn: array{float, float}, decimalColumn: array{numeric-string&uppercase-string, numeric-string&uppercase-string}, stringColumn: array{string, string}, stringNullColumn: array{string|null, string|null}, mixedColumn: array{mixed, mixed}}',
			$unitOfWork->getEntityChangeSet($entity)
		);
	}

	public function associations(UnitOfWork $unitOfWork, Many $entity): void
	{
		assertType(
			'array{intColumn: array{int, int}, stringColumn: array{string, string}, stringNullColumn: array{string|null, string|null}, datetimeColumn: array{DateTime, DateTime}, datetimeImmutableColumn: array{DateTimeImmutable, DateTimeImmutable}, simpleArrayColumn: array{list<string>, list<string>}, one: array{QueryResult\\Entities\\One, QueryResult\\Entities\\One}, oneNull: array{QueryResult\\Entities\\One|null, QueryResult\\Entities\\One|null}, oneDefaultNullability: array{QueryResult\\Entities\\One|null, QueryResult\\Entities\\One|null}, compoundPk: array{QueryResult\\Entities\\CompoundPk|null, QueryResult\\Entities\\CompoundPk|null}, compoundPkAssoc: array{QueryResult\\Entities\\CompoundPkAssoc|null, QueryResult\\Entities\\CompoundPkAssoc|null}}',
			$unitOfWork->getEntityChangeSet($entity)
		);
	}

	public function persistentCollection(UnitOfWork $unitOfWork, One $entity): void
	{
		$changeSet = $unitOfWork->getEntityChangeSet($entity);
		assertType('array{Doctrine\\ORM\\PersistentCollection, Doctrine\\ORM\\PersistentCollection}', $changeSet['manies']);
	}

	public function unknownEntity(UnitOfWork $unitOfWork, object $entity): void
	{
		assertType(
			'array<string, array{mixed, mixed}|Doctrine\\ORM\\PersistentCollection>',
			$unitOfWork->getEntityChangeSet($entity)
		);
	}
}
