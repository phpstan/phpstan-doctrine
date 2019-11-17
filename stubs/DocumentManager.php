<?php

namespace Doctrine\ODM\MongoDB;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class DocumentManager implements ObjectManager
{

	/**
	 * @template T
	 * @param class-string<T> $documentName
	 * @param mixed  $identifier
	 * @param integer|null $lockMode
	 * @param integer|null $lockVersion
	 * @return T|null
	 */
	public function find($documentName, $identifier, $lockMode = null, $lockVersion = null);

	/**
	 * @template T
	 * @param T $document
	 * @return T
	 */
	public function merge($document);

	/**
	 * @template T
	 * @param class-string<T> $documentName
	 * @return DocumentRepository<T>
	 */
	public function getRepository($documentName);

	/**
	 * @template T
	 * @param class-string<T> $documentName
	 * @param mixed $identifier
	 * @return T|null
	 */
	public function getReference($documentName, $identifier);

	/**
	 * @template T
	 * @param class-string<T> $documentName
	 * @param mixed $identifier
	 *
	 * @return T|null
	 */
	public function getPartialReference($documentName, $identifier);

	/**
	 * @template T
	 * @param T $entity
	 * @param bool $deep
	 * @return T
	 */
	public function copy($entity, $deep = false);

}
