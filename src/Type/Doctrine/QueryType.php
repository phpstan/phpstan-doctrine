<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Type\ObjectType;

class QueryType extends ObjectType
{

	/** @var string */
	private $dql;

	public function __construct(string $dql)
	{
		parent::__construct('Doctrine\ORM\Query');
		$this->dql = $dql;
	}

	public function getDql(): string
	{
		return $this->dql;
	}

}
