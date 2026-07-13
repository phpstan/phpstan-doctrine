<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPUnit\Framework\TestCase;
use QueryResult\MultipleEntityManagers\Main\User;
use QueryResult\MultipleEntityManagers\Tenant\App;

class ObjectMetadataResolverMultipleEntityManagersTest extends TestCase
{

	private ObjectMetadataResolver $resolver;

	protected function setUp(): void
	{
		$this->resolver = new ObjectMetadataResolver(
			__DIR__ . '/data/QueryResult/entity-manager-selection-registry.php',
			__DIR__ . '/../../../tmp',
		);
	}

	/**
	 * @dataProvider dqlManagerProvider
	 */
	public function testSelectsObjectManagerFromDqlEntityClass(string $dql, string $managerName): void
	{
		self::assertSame(
			$this->resolver->getObjectManagerByName($managerName),
			$this->resolver->getObjectManagerForDql($dql),
		);
	}

	/** @return iterable<string, array{string, string}> */
	public static function dqlManagerProvider(): iterable
	{
		yield 'select with explicit AS alias' => [
			'SELECT a FROM ' . App::class . ' AS a',
			'tenant',
		];

		yield 'lowercase select keyword' => [
			'select a from ' . App::class . ' a',
			'tenant',
		];

		yield 'partial object select' => [
			'SELECT PARTIAL a.{id} FROM ' . App::class . ' a',
			'tenant',
		];

		yield 'root entity before subquery' => [
			'SELECT u FROM ' . User::class . ' u WHERE u.id IN (SELECT a.id FROM ' . App::class . ' a)',
			'default',
		];

		yield 'update query' => [
			'UPDATE ' . App::class . ' a SET a.id = :id',
			'tenant',
		];

		yield 'delete query with FROM' => [
			'DELETE FROM ' . App::class . ' a',
			'tenant',
		];
	}

	public function testIgnoresClassLikeTextInsideDqlStringLiterals(): void
	{
		$dql = "SELECT 'FROM " . App::class . " a', 'DELETE FROM " . App::class . " a' FROM " . User::class . ' u';

		self::assertSame(
			$this->resolver->getObjectManagerByName('default'),
			$this->resolver->getObjectManagerForDql($dql),
		);
	}

	public function testIgnoresClassLikeTextInsideEscapedDqlStringLiterals(): void
	{
		$dql = "SELECT 'not '' FROM " . App::class . " a' FROM " . User::class . ' u';

		self::assertSame(
			$this->resolver->getObjectManagerByName('default'),
			$this->resolver->getObjectManagerForDql($dql),
		);
	}

}
