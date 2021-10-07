<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\CustomObjectManager;

use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;

/**
 * @Document
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
