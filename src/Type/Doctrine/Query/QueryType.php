<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IsSuperTypeOfResult;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

/** @api */
class QueryType extends GenericObjectType
{

	private Type $indexType;

	private Type $resultType;

	private string $dql;

	public function __construct(string $dql, ?Type $indexType = null, ?Type $resultType = null, ?Type $subtractedType = null)
	{
		$this->indexType = $indexType ?? new MixedType();
		$this->resultType = $resultType ?? new MixedType();

		parent::__construct('Doctrine\ORM\Query', [
			$this->indexType,
			$this->resultType,
		], $subtractedType);

		$this->dql = $dql;
	}

	public function equals(Type $type): bool
	{
		if ($type instanceof self) {
			return $this->getDql() === $type->getDql();
		}

		return parent::equals($type);
	}

	public function changeSubtractedType(?Type $subtractedType): Type
	{
		return new self('Doctrine\ORM\Query', $this->indexType, $this->resultType, $subtractedType);
	}

	public function isSuperTypeOf(Type $type): IsSuperTypeOfResult
	{
		if ($type instanceof self) {
			return IsSuperTypeOfResult::createFromBoolean($this->equals($type));
		}

		return parent::isSuperTypeOf($type);
	}

	public function getDql(): string
	{
		return $this->dql;
	}

}
