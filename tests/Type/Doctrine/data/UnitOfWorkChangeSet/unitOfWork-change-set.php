<?php declare(strict_types = 1);

namespace UnitOfWorkChangeSet;

use Doctrine\ORM\UnitOfWork;
use UnitOfWorkChangeSet\Entities\SimpleEntity;
use function PHPStan\Testing\assertType;

final class UnitOfWorkChangeSetAssertions
{
	public function simpleField(UnitOfWork $unitOfWork, SimpleEntity $entity): void
	{
		assertType(
			'array{foo: array{int, int}, nullableFoo: array{int|null, int|null}, related: array{UnitOfWorkChangeSet\\Entities\\RelatedEntity|null, UnitOfWorkChangeSet\\Entities\\RelatedEntity|null}, relatedCollection: array{Doctrine\\ORM\\PersistentCollection, Doctrine\\ORM\\PersistentCollection}}',
			$unitOfWork->getEntityChangeSet($entity)
		);
	}

	public function unknownEntity(UnitOfWork $unitOfWork, object $entity): void
	{
		assertType(
			'array<string, array{mixed, mixed}|Doctrine\\ORM\\PersistentCollection>',
			$unitOfWork->getEntityChangeSet($entity)
		);
	}
}
