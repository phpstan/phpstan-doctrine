<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

class ObjectRepositoryType extends ObjectType
{

	/** @var string */
	private $entityClass;

	public function __construct(string $entityClass, string $repositoryClass)
	{
		parent::__construct($repositoryClass);
		$this->entityClass = $entityClass;
	}

	public function getEntityClass(): string
	{
		return $this->entityClass;
	}

	public function describe(VerbosityLevel $level): string
	{
		return sprintf('%s<%s>', parent::describe($level), $this->entityClass);
	}

	public static function __set_state(array $properties): Type
	{
		return new self($properties['entityClass'], $properties['className']);
	}

}
