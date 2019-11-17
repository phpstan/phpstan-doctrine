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
	 * @param mixed $id
	 * @param int|null $lockMode
	 * @param int|null $lockVersion
	 * @return TDocumentClass|null
	 */
	public function find($id, $lockMode = null, $lockVersion = null);

	/**
	 * @return TDocumentClass[]
	 */
	public function findAll();

	/**
	 * @param mixed[] $criteria
	 * @param string[]|null $sort
	 * @param int|null $limit
	 * @param int|null $sip
	 * @return TDocumentClass[]
	 */
	public function findBy(array $criteria, ?array $sort = null, $limit = null, $skip = null);

	/**
	 * @param mixed[] $criteria The criteria.
	 * @return TDocumentClass|null
	 */
	public function findOneBy(array $criteria);

	/**
	 * @return class-string<TDocumentClass>
	 */
	public function getClassName();

}
