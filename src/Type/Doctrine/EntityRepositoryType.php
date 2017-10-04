<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Type\ObjectType;

class EntityRepositoryType extends ObjectType
{

	/**
	 * @var string
	 */
	private $entityClass;

	public function __construct(string $entityClass)
	{
		parent::__construct(\Doctrine\ORM\EntityRepository::class);
		$this->entityClass = $entityClass;
	}

	public function getEntityClass(): string
	{
		return $this->entityClass;
	}

	public function describe(): string
	{
		return sprintf('%s<%s>', parent::describe(), $this->entityClass);
	}

}
