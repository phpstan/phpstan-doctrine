<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ODM\DocumentRepositoryDynamicReturn;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use RuntimeException;
use function PHPStan\Testing\assertType;

class Example
{
	/**
	 * @var DocumentRepository
	 */
	private $repository;

	public function __construct(DocumentManager $documentManager)
	{
		$this->repository = $documentManager->getRepository(MyDocument::class);
	}

	public function findDynamicType(): void
	{
		$test = $this->repository->find(1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType('object', $test);

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findOneByDynamicType(): void
	{
		$test = $this->repository->findOneBy(['blah' => 'testing']);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType('object', $test);

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findAllDynamicType(): void
	{
		$items = $this->repository->findAll();
		assertType('array<int, object>', $items);

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}

	public function findByDynamicType(): void
	{
		$items = $this->repository->findBy(['blah' => 'testing']);
		assertType('array<int, object>', $items);

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}
}

class Example2
{
	/**
	 * @var DocumentRepository<MyDocument>
	 */
	private $repository;

	public function __construct(DocumentManager $documentManager)
	{
		$this->repository = $documentManager->getRepository(MyDocument::class);
	}

	public function findDynamicType(): void
	{
		$test = $this->repository->find(1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType(MyDocument::class, $test);

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findOneByDynamicType(): void
	{
		$test = $this->repository->findOneBy(['blah' => 'testing']);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType(MyDocument::class, $test);

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function findAllDynamicType(): void
	{
		$items = $this->repository->findAll();
		assertType('array<int, ' . MyDocument::class . '>', $items);

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}

	public function findByDynamicType(): void
	{
		$items = $this->repository->findBy(['blah' => 'testing']);
		assertType('array<int, ' . MyDocument::class . '>', $items);

		foreach ($items as $test) {
			$test->doSomething();
			$test->doSomethingElse();
		}
	}
}

/**
 * @Document()
 */
class MyDocument
{
	/**
	 * @Id(strategy="NONE", type="string")
	 *
	 * @var string
	 */
	private $id;

	public function doSomething(): void
	{
	}
}
