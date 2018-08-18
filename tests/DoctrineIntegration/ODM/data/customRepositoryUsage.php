<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ODM\CustomRepositoryUsage;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use RuntimeException;

class Example
{
	/**
	 * @var MyRepository
	 */
	private $repository;

	public function __construct(DocumentManager $documentManager)
	{
		$this->repository = $documentManager->getRepository(MyDocument::class);
	}

	public function get(): void
	{
		$test = $this->repository->get('testing');
		$test->doSomethingElse();
	}
}

/**
 * @Document(repositoryClass=MyRepository::class)
 */
class MyDocument
{
	/**
	 * @Id(strategy="NONE", type="string")
	 *
	 * @var string
	 */
	private $id;

	public function doSomethingElse(): void
	{
	}
}

class MyRepository extends DocumentRepository
{
	public function get(string $id): MyDocument
	{
		$document = $this->find($id);

		if ($document === null) {
			throw new RuntimeException('Not found...');
		}

		return $document;
	}
}
