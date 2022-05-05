<?php

namespace PHPStan\DoctrineIntegration\ORM\GetClassMetadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MyRepository::class)
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
}

/**
 * @template T of object
 * @template-extends EntityRepository<T>
 */
class IntermediateRepository extends EntityRepository
{
	/**
	 * @param ClassMetadata<T> $class
	 */
	public function __construct(EntityManagerInterface $em, ClassMetadata $class)
	{
		parent::__construct($em, $class);
	}
}

/**
 * @extends IntermediateRepository<MyEntity>
 */
class MyRepository extends IntermediateRepository
{

	public function __construct(
		EntityManagerInterface $manager
	) {
		parent::__construct($manager, $manager->getClassMetadata(MyEntity::class));
	}
}
