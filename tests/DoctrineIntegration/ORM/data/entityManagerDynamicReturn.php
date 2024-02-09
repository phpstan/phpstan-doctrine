<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\EntityManagerDynamicReturn;

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

	public function findDynamicType(): void
	{
		$test = $this->entityManager->find(MyEntity::class, 1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
		$test->doSomethingElse();
	}

	public function getReferenceDynamicType(): void
	{
		$test = $this->entityManager->getReference(MyEntity::class, 1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType(MyEntity::class, $test);

		$test->doSomething();
		$test->doSomethingElse();
	}

	/**
	 * @param class-string $entityName
	 */
	public function doSomethingWithRepository(string $entityName): void
	{
		$repository = $this->entityManager->getRepository($entityName);
		$repository->getClassName();
		$repository->unknownMethod();
		assertType('Doctrine\ORM\EntityRepository<object>', $repository);
		$entity = $repository->find(1);

		if ($entity === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		assertType('object', $entity);

		$entity->unknownMethod();
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
