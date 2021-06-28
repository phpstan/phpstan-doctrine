<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use JsonSerializable;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use stdClass;

class JsonType implements DoctrineTypeDescriptor
{

	public function getType(): string
	{
		return \Doctrine\DBAL\Types\JsonType::class;
	}

	public function getWritableToPropertyType(): Type
	{
		return new \PHPStan\Type\UnionType([
			new ArrayType(new MixedType(), new MixedType()),
			new BooleanType(),
			new FloatType(),
			new IntegerType(),
			new NullType(),
			new ObjectType(JsonSerializable::class),
			new ObjectType(stdClass::class),
			new StringType(),
		]);
	}

	public function getWritableToDatabaseType(): Type
	{
		return new \PHPStan\Type\UnionType([
			new ArrayType(new MixedType(), new MixedType()),
			new BooleanType(),
			new FloatType(),
			new IntegerType(),
			new NullType(),
			new ObjectType(JsonSerializable::class),
			new ObjectType(stdClass::class),
			new StringType(),
		]);
	}

}
