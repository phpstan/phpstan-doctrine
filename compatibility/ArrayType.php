<?php

namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function is_resource;
use function restore_error_handler;
use function serialize;
use function set_error_handler;
use function stream_get_contents;
use function unserialize;

use const E_DEPRECATED;
use const E_USER_DEPRECATED;

/**
 * Type that maps a PHP array to a clob SQL type.
 *
 * @deprecated Use {@link JsonType} instead.
 */
class ArrayType extends Type
{
	public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
	{
		return $platform->getClobTypeDeclarationSQL($column);
	}

	public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
	{
		// @todo 3.0 - $value === null check to save real NULL in database
		return serialize($value);
	}

	public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
	{
		if ($value === null) {
			return null;
		}

		$value = is_resource($value) ? stream_get_contents($value) : $value;

		set_error_handler(function (int $code, string $message): bool {
			if ($code === E_DEPRECATED || $code === E_USER_DEPRECATED) {
				return false;
			}

			throw ConversionException::conversionFailedUnserialization($this->getName(), $message);
		});

		try {
			return unserialize($value);
		} finally {
			restore_error_handler();
		}
	}
}
