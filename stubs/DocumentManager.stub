<?php

namespace Doctrine\ODM\MongoDB;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

class DocumentManager implements ObjectManager
{

	/**
	 * @template T
	 * @phpstan-param class-string<T> $documentName
	 * @phpstan-param mixed  $identifier
	 * @phpstan-param integer|null $lockMode
	 * @phpstan-param integer|null $lockVersion
	 * @phpstan-return T|null
	 */
	public function find($documentName, $identifier, $lockMode = null, $lockVersion = null);

	/**
	 * @template T
	 * @phpstan-param T $document
	 * @phpstan-return T
	 */
	public function merge($document);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $documentName
	 * @phpstan-return DocumentRepository<T>
	 */
	public function getRepository($documentName);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $documentName
	 * @phpstan-param mixed $identifier
	 * @phpstan-return T|null
	 */
	public function getReference($documentName, $identifier);

	/**
	 * @template T
	 * @phpstan-param class-string<T> $documentName
	 * @phpstan-param mixed $identifier
	 *
	 * @phpstan-return T|null
	 */
	public function getPartialReference($documentName, $identifier);

	/**
	 * @template T
	 * @phpstan-param T $entity
	 * @phpstan-param bool $deep
	 * @phpstan-return T
	 */
	public function copy($entity, $deep = false);

}
