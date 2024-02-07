<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\EntityManagerMergeReturn;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;
use function PHPStan\Testing\assertType;

class Example
{
	/**
	 * @var EntityManager
	 */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	public function merge(): void
	{
		$test = $this->entityManager->merge(new MyEntity());
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
