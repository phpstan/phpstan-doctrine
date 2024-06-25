<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

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
	 *
	 * This is not used for direct column type inferring,
	 * but when such column appears in expression like SELECT MAX(e.field)
	 *
	 * Sometimes, the type cannot be reliably decided without driver context,
	 * use DoctrineTypeDriverAwareDescriptor in such cases
	 */
	public function getDatabaseInternalType(): Type;

}
