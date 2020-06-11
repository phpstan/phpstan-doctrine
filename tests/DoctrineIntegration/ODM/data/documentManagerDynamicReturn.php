<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ODM\DocumentManagerDynamicReturn;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use RuntimeException;

class Example
{
	/**
	 * @var DocumentManager
	 */
	private $documentManager;

	public function __construct(DocumentManager $documentManager)
	{
		$this->documentManager = $documentManager;
	}

	public function findDynamicType(): void
	{
		$test = $this->documentManager->find(MyDocument::class, 'blah-123');

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function getReferenceDynamicType(): void
	{
		$test = $this->documentManager->getReference(MyDocument::class, 'blah-123');
		$test->doSomething();
		$test->doSomethingElse();
	}

	public function getPartialReferenceDynamicType(): void
	{
		$test = $this->documentManager->getPartialReference(MyDocument::class, 'blah-123');
		$test->doSomething();
		$test->doSomethingElse();
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
