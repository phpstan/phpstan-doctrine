<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

class MagicRepositoryMethodReflection implements MethodReflection
{

	/** @var \PHPStan\Reflection\ClassReflection */
	private $declaringClass;

	/** @var string */
	private $name;

	/** @var Type */
	private $type;

	public function __construct(
		ClassReflection $declaringClass,
		string $name,
		Type $type
	)
	{
		$this->declaringClass = $declaringClass;
		$this->name = $name;
		$this->type = $type;
	}

	public function getDeclaringClass(): \PHPStan\Reflection\ClassReflection
	{
		return $this->declaringClass;
	}

	public function isStatic(): bool
	{
		return false;
	}

	public function isPrivate(): bool
	{
		return false;
	}

	public function isPublic(): bool
	{
		return true;
	}

	public function getDocComment(): ?string
	{
		return null;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getPrototype(): \PHPStan\Reflection\ClassMemberReflection
	{
		return $this;
	}

	public function getVariants(): array
	{
		if (strpos($this->name, 'findBy') === 0) {
			$arguments = [
				new DummyParameter('argument', new MixedType(), false, null, false, null),
				new DummyParameter('orderBy', new UnionType([new ArrayType(new StringType(), new StringType()), new NullType()]), true, null, false, null),
				new DummyParameter('limit', new UnionType([new IntegerType(), new NullType()]), true, null, false, null),
				new DummyParameter('offset', new UnionType([new IntegerType(), new NullType()]), true, null, false, null),
			];
		} elseif (strpos($this->name, 'findOneBy') === 0) {
			$arguments = [
				new DummyParameter('argument', new MixedType(), false, null, false, null),
				new DummyParameter('orderBy', new UnionType([new ArrayType(new StringType(), new StringType()), new NullType()]), true, null, false, null),
			];
		} elseif (strpos($this->name, 'countBy') === 0) {
			$arguments = [
				new DummyParameter('argument', new MixedType(), false, null, false, null),
			];
		} else {
			throw new \PHPStan\ShouldNotHappenException();
		}

		return [
			new FunctionVariant(
				TemplateTypeMap::createEmpty(),
				null,
				$arguments,
				false,
				$this->type
			),
		];
	}

	public function isDeprecated(): \PHPStan\TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getDeprecatedDescription(): ?string
	{
		return null;
	}

	public function isFinal(): \PHPStan\TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function isInternal(): \PHPStan\TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

	public function getThrowType(): ?Type
	{
		return null;
	}

	public function hasSideEffects(): \PHPStan\TrinaryLogic
	{
		return TrinaryLogic::createNo();
	}

}
