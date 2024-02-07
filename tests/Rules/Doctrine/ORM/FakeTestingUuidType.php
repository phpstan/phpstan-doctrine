<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\GuidType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;
use function is_object;
use function is_string;
use function method_exists;

/**
 * From https://github.com/ramsey/uuid-doctrine/blob/fafebbe972cdaba9274c286ea8923e2de2579027/src/UuidType.php
 * Copyright (c) 2012-2022 Ben Ramsey <ben@benramsey.com>
 */
final class FakeTestingUuidType extends GuidType
{

	public const NAME = 'uuid';

	/**
	 * {@inheritdoc}
	 *
	 * @throws ConversionException
	 */
	public function convertToPHPValue($value, AbstractPlatform $platform): ?UuidInterface
	{
		if ($value instanceof UuidInterface) {
			return $value;
		}

		if (!is_string($value) || $value === '') {
			return null;
		}

		try {
			$uuid = Uuid::fromString($value);
		} catch (Throwable $e) {
			throw ConversionException::conversionFailed($value, self::NAME);
		}

		return $uuid;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws ConversionException
	 */
	public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (
			$value instanceof UuidInterface
			|| (
				(is_string($value)
					|| (is_object($value) && method_exists($value, '__toString')))
				&& Uuid::isValid((string) $value)
			)
		) {
			return (string) $value;
		}

		throw ConversionException::conversionFailed($value, self::NAME);
	}

	public function getName(): string
	{
		return self::NAME;
	}

	public function requiresSQLCommentHint(AbstractPlatform $platform): bool
	{
		return true;
	}

	/**
	 * @return string[]
	 */
	public function getMappedDatabaseTypes(AbstractPlatform $platform): array
	{
		return [self::NAME];
	}

}
