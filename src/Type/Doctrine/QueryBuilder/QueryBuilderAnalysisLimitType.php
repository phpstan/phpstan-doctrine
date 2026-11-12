<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\QueryBuilder;

use PHPStan\Type\ObjectType;

final class QueryBuilderAnalysisLimitType extends ObjectType
{

	private int $limit;

	public function __construct(int $limit)
	{
		parent::__construct('Doctrine\ORM\Query');
		$this->limit = $limit;
	}

	public function getLimit(): int
	{
		return $this->limit;
	}

}
