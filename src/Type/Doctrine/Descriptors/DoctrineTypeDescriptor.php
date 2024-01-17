<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Driver;
use PHPStan\Type\Type;

/** @api */
interface DoctrineTypeDescriptor
{

	/**
	 * @return class-string<\Doctrine\DBAL\Types\Type>
	 */
	public function getType(): string;

	/**
	 * This is used for inferring direct column results, e.g. SELECT e.field
	 * It should comply with convertToPHPValue return value
	 */
	public function getWritableToPropertyType(): Type;

	public function getWritableToDatabaseType(): Type;

	/**
	 * This is used for inferring how database fetches column of such type
	 * It should return the native type without stringification that may occur on certain PHP versions or driver configuration
	 *
	 * This is not used for direct column type inferring,
	 * but when such column appears in expression like SELECT MAX(e.field)
	 *
	 * See: https://github.com/janedbal/php-database-drivers-fetch-test
	 *
	 *              mysql   sqlite  pdo_pgsql    pgsql
	 * - decimal:  string    float     string   string
	 * - float:    float     float     string    float
	 * - bigint:   int         int        int      int
	 * - bool:     int         int       bool     bool
	 */
	public function getDatabaseInternalType(Driver $driver): Type;

}
