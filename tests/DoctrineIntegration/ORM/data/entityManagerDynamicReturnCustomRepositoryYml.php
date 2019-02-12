<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\ORM\EntityManagerDynamicReturnCustomRepositoryYml;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
		$test = $this->entityManager->getRepository(MyEntity::class)->findMyEntity(1);

		if ($test === null) {
			throw new RuntimeException('Sorry, but no...');
		}

		$test->doSomething();
	}

	public function errorDynamicType(): void
	{
		$this->entityManager->getRepository(MyEntity::class)->nonexistant();
	}
}

class MyEntity
{
	private $id;

	public function doSomething(): void
	{
	}
}

class MyEntityRepository extends EntityRepository
{
	public function findMyEntity($id): ?MyEntity
	{
		return $this->findOneBy([
			'id' => $id
		]);
	}
}
