<?php

namespace Doctrine\ODM\MongoDB\Repository;

use Doctrine\Common\Persistence\ObjectRepository;

/**
 * @template TDocumentClass
 * @implements ObjectRepository<TDocumentClass>
 */
class DocumentRepository implements ObjectRepository
{

	/**
	 * @phpstan-param mixed $id
	 * @phpstan-param int|null $lockMode
	 * @phpstan-param int|null $lockVersion
	 * @phpstan-return TDocumentClass|null
	 */
	public function find($id, $lockMode = null, $lockVersion = null);

	/**
	 * @phpstan-return TDocumentClass[]
	 */
	public function findAll();

	/**
	 * @phpstan-param mixed[] $criteria
	 * @phpstan-param string[]|null $sort
	 * @phpstan-param int|null $limit
	 * @phpstan-param int|null $sip
	 * @phpstan-return TDocumentClass[]
	 */
	public function findBy(array $criteria, ?array $sort = null, $limit = null, $skip = null);

	/**
	 * @phpstan-param mixed[] $criteria The criteria.
	 * @phpstan-return TDocumentClass|null
	 */
	public function findOneBy(array $criteria);

	/**
	 * @phpstan-return class-string<TDocumentClass>
	 */
	public function getClassName();

}
