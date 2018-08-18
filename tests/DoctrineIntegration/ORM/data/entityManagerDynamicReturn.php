<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\EntityManagerDynamicReturn;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

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

	public function findDynamicType(): void
	{
		$test = $this->entityManager->find(MyEntity::class, 1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
	}

	public function getReferenceDynamicType(): void
	{
		$test = $this->entityManager->getReference(MyEntity::class, 1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
	}

	public function getPartialReferenceDynamicType(): void
	{
		$test = $this->entityManager->getPartialReference(MyEntity::class, 1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
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
