<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Php\DummyParameter;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;

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

	/**
	 * @return string|false
	 */
	public function getDocComment()
	{
		return false;
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
		return [
			new FunctionVariant(
				TemplateTypeMap::createEmpty(),
				null,
				[
					new DummyParameter('parameter', new MixedType(), false, null, false, null),
				],
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
