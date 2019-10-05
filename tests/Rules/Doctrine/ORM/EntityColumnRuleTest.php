<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\BigIntType;
use PHPStan\Type\Doctrine\Descriptors\BinaryType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeImmutableType;
use PHPStan\Type\Doctrine\Descriptors\DateTimeType;
use PHPStan\Type\Doctrine\Descriptors\StringType;
use PHPStan\Type\Doctrine\ObjectMetadataResolver;

class EntityColumnRuleTest extends RuleTestCase
{

	protected function getRule(): Rule
	{
		return new EntityColumnRule(
			new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null),
			new DescriptorRegistry([
				new BigIntType(),
				new StringType(),
				new DateTimeType(),
				new DateTimeImmutableType(),
				new BinaryType(),
			])
		);
	}

	public function testRule(): void
	{
		require_once __DIR__ . '/data/MyBrokenSuperclass.php';
		$this->analyse([__DIR__ . '/data/MyBrokenEntity.php'], [
			[
				'Database can contain string but property expects int.',
				17,
			],
			[
				'Database can contain string|null but property expects string.',
				23,
			],
			[
				'Property can contain string|null but database expects string.',
				29,
			],
			[
				'Database can contain DateTime but property expects DateTimeImmutable.',
				35,
			],
			[
				'Database can contain DateTimeImmutable but property expects DateTime.',
				41,
			],
			[
				'Property can contain DateTime but database expects DateTimeImmutable.',
				41,
			],
		]);
	}

	public function testSuperclass(): void
	{
		$this->analyse([__DIR__ . '/data/MyBrokenSuperclass.php'], [
			[
				'Database can contain resource but property expects int.',
				17,
			],
		]);
	}

}
