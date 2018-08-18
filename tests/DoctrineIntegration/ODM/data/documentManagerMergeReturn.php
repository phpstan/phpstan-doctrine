<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ODM\DocumentManagerMergeReturn;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;

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

	public function merge(): void
	{
		$test = $this->documentManager->merge(new MyDocument());
		$test->doSomething();
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
