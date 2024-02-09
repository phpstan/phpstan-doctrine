<?php

namespace PHPStan\DoctrineIntegration\ORM\EntityManagerOrm2;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;
use function PHPStan\Testing\assertType;

class Example
{
	/**
	 * @var EntityManagerInterface
	 */
	private $entityManager;

	public function __construct(EntityManagerInterface $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function getPartialReferenceDynamicType(): void
	{
		$test = $this->entityManager->getPartialReference(MyEntity::class, 1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType(MyEntity::class, $test);

		$test->doSomething();
		$test->doSomethingElse();
	}
}

/**
 * @ORM\Entity()
 */
class MyEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 *
	 * @var int
	 */
	private $id;

	public function doSomething(): void
	{
	}
}
