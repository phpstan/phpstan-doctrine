<?php

namespace ClassMetadataIndexMapping;

use Doctrine\ORM\Mapping\ClassMetadata;
use function PHPStan\Testing\assertType;

class Foo
{

	/**
	 * @param ClassMetadata<object> $metadata
	 */
	public function doFoo(ClassMetadata $metadata): void
	{
		assertType('array<array{name?: string, columns?: list<string>, fields?: non-empty-list<string>, flags?: array<mixed>, options?: array<mixed>}>|null', $metadata->table['uniqueConstraints'] ?? null);
	}

}
