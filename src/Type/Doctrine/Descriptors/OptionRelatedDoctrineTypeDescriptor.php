<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Descriptors;

use PHPStan\Type\Type;

/** @api */
interface OptionRelatedDoctrineTypeDescriptor extends DoctrineTypeDescriptor
{

	/**
	 * @return class-string<\Doctrine\DBAL\Types\Type>
	 */
	public function getType(): string;

	/**
	 * @param array<string, mixed> $options
	 */
	public function getWritableToPropertyType(array $options = []): Type;

	/**
	 * @param array<string, mixed> $options
	 */
	public function getWritableToDatabaseType(array $options = []): Type;

	/**
	 * @param array<string, mixed> $options
	 */
	public function getDatabaseInternalType(array $options = []): Type;

}
