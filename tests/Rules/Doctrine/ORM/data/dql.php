<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\ORM\EntityManager;

class TestRepository
{

	/** @var EntityManager */
	private $entityManager;

	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * @return MyEntity[]
	 */
	public function getEntities(): array
	{
		return $this->entityManager->createQuery(
			'SELECT e FROM ' . MyEntity::class . ' e'
		)->getResult();
	}

	public function parseError(): void
	{
		$this->entityManager->createQuery(
			'SELECT e FROM ' . MyEntity::class
		)->getResult();
	}

	public function unknownField(): void
	{
		$this->entityManager->createQuery(
			'SELECT e FROM ' . MyEntity::class . ' e WHERE e.transient = :test'
		)->getResult();
	}

	public function unknownEntity(): void
	{
		$this->entityManager->createQuery(
			'SELECT e FROM Foo e'
		)->getResult();
	}

}
