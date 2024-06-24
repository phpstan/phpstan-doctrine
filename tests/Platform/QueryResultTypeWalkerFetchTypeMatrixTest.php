<?php declare(strict_types = 1);

namespace PHPStan\Platform;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use mysqli;
use PDO;
use PHPStan\Platform\Entity\PlatformEntity;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Query\QueryResultTypeBuilder;
use PHPStan\Type\Doctrine\Query\QueryResultTypeWalker;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Constraint\IsType;
use SQLite3;
use function array_column;
use function array_combine;
use function array_fill;
use function array_keys;
use function class_exists;
use function function_exists;
use function get_debug_type;
use function getenv;
use function gettype;
use function is_a;
use function is_resource;
use function method_exists;
use function reset;
use function sprintf;
use function strpos;
use function var_export;
use const MYSQLI_OPT_INT_AND_FLOAT_NATIVE;
use const PHP_MAJOR_VERSION;
use const PHP_MINOR_VERSION;

/**
 * This test ensures our query type inferring never differs from actual result types produced by PHP, Database drivers and Doctrine (with various versions and configurations).
 *
 * @group platform
 */
final class QueryResultTypeWalkerFetchTypeMatrixTest extends PHPStanTestCase
{

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/data/config.neon',
		];
	}

	/**
	 * @param array<string, mixed> $connectionParams
	 * @param array<string, string|null> $expectedOnPhp80AndBelow
	 * @param array<string, string|null> $expectedOnPhp81AndAbove
	 * @param array<string, mixed> $connectionAttributes
	 *
	 * @dataProvider provideCases
	 */
	public function testFetchedTypes(
		array $connectionParams,
		array $expectedOnPhp80AndBelow,
		array $expectedOnPhp81AndAbove,
		array $connectionAttributes
	): void
	{
		$phpVersion = PHP_MAJOR_VERSION * 10 + PHP_MINOR_VERSION;

		try {
			$connection = DriverManager::getConnection($connectionParams + [
				'user' => 'root',
				'password' => 'secret',
				'dbname' => 'foo',
			]);

			$nativeConnection = $this->getNativeConnection($connection);
			$this->setupAttributes($nativeConnection, $connectionAttributes);

			$config = new Configuration();
			$config->setProxyNamespace('PHPstan\Doctrine\OrmMatrixProxies');
			$config->setProxyDir('/tmp/doctrine');
			$config->setAutoGenerateProxyClasses(false);
			$config->setSecondLevelCacheEnabled(false);
			$config->setMetadataCache(new ArrayCachePool());

			if (InstalledVersions::satisfies(new VersionParser(), 'doctrine/orm', '3.*')) {
				$config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));
			} else {
				$config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader(), [__DIR__ . '/Entity']));
			}

			$entityManager = new EntityManager($connection, $config);

		} catch (DbalException $e) {
			if (strpos($e->getMessage(), 'Doctrine currently supports only the following drivers') !== false) {
				self::markTestSkipped($e->getMessage()); // older doctrine versions, needed for old PHP versions
			}
			throw $e;
		}

		$schemaTool = new SchemaTool($entityManager);
		$classes = $entityManager->getMetadataFactory()->getAllMetadata();
		$schemaTool->dropSchema($classes);
		$schemaTool->createSchema($classes);

		$entity = new PlatformEntity();
		$entity->id = '1';
		$entity->col_bool = true;
		$entity->col_float = 0.125;
		$entity->col_decimal = '0.1';
		$entity->col_int = 9;
		$entity->col_bigint = '2147483648';
		$entity->col_string = 'foobar';

		$entityManager->persist($entity);
		$entityManager->flush();

		$columnsQueryTemplate = 'SELECT %s FROM %s t GROUP BY t.col_int, t.col_float, t.col_decimal, t.col_bigint, t.col_bool, t.col_string';

		$expected = $phpVersion >= 81
			? $expectedOnPhp81AndAbove
			: $expectedOnPhp80AndBelow;

		foreach ($expected as $select => $expectedType) {
			if ($expectedType === null) {
				continue; // e.g. no such function
			}
			$dql = sprintf($columnsQueryTemplate, $select, PlatformEntity::class);

			$query = $entityManager->createQuery($dql);
			$result = $query->getSingleResult();

			$typeBuilder = new QueryResultTypeBuilder();
			QueryResultTypeWalker::walk($query, $typeBuilder, self::getContainer()->getByType(DescriptorRegistry::class));

			$inferredPhpStanType = $typeBuilder->getResultType();
			$realRowPhpStanType = ConstantTypeHelper::getTypeFromValue($result);

			$firstResult = reset($result);
			$resultType = gettype($firstResult);
			$resultExported = var_export($firstResult, true);

			self::assertTrue(
				$inferredPhpStanType->accepts($realRowPhpStanType, true)->yes(),
				sprintf(
					"Result of 'SELECT %s' for '%s' and PHP %s was inferred as %s, but the real result was %s",
					$select,
					$this->dataName(),
					$phpVersion,
					$inferredPhpStanType->describe(VerbosityLevel::precise()),
					$realRowPhpStanType->describe(VerbosityLevel::precise())
				)
			);

			self::assertThat(
				$firstResult,
				new IsType($expectedType),
				sprintf(
					"Result of 'SELECT %s' for '%s' and PHP %s is expected to be %s, but %s returned (%s).",
					$select,
					$this->dataName(),
					$phpVersion,
					$expectedType,
					$resultType,
					$resultExported
				)
			);
		}
	}

	/**
	 * @return iterable<string, mixed>
	 */
	public function provideCases(): iterable
	{
		// Preserve space-driven formatting for better readability
		// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore
		// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingAfter

		// Notes:
		// - Any direct column fetch uses the type declared in entity, but when passed to a function, the driver decides the type

		$testData = [                          // mysql,          sqlite,     pdo_pgsql,     pgsql,    stringified, stringifiedOldPostgre
			// bool-ish
			'(TRUE)' =>                         ['int',          'int',      'bool',       'bool',    'string',       'bool'],
			't.col_bool' =>                     ['bool',         'bool',     'bool',       'bool',    'bool',         'bool'],
			'COALESCE(t.col_bool, TRUE)' =>     ['int',          'int',      'bool',       'bool',    'string',       'bool'],

			// float-ish
			't.col_float' =>                    ['float',        'float',    'float',     'float',    'float',      'float'],
			'AVG(t.col_float)' =>               ['float',        'float',    'string',     'float',   'string',     'string'],
			'SUM(t.col_float)' =>               ['float',        'float',    'string',     'float',   'string',     'string'],
			'MIN(t.col_float)' =>               ['float',        'float',    'string',     'float',   'string',     'string'],
			'MAX(t.col_float)' =>               ['float',        'float',    'string',     'float',   'string',     'string'],
			'SQRT(t.col_float)' =>              ['float',        'float',    'string',     'float',   'string',     'string'],
			'ABS(t.col_float)' =>               ['float',        'float',    'string',     'float',   'string',     'string'],

			// decimal-ish
			't.col_decimal' =>                  ['string',       'string',   'string',     'string',  'string',     'string'],
			'0.1' =>                            ['string',       'float',    'string',     'string',  'string',     'string'],
			'0.125e0' =>                        ['float',        'float',    'string',     'string',  'string',     'string'],
			'AVG(t.col_decimal)' =>             ['string',       'float',    'string',     'string',  'string',     'string'],
			'AVG(t.col_int)' =>                 ['string',       'float',    'string',     'string',  'string',     'string'],
			'AVG(t.col_bigint)' =>              ['string',       'float',    'string',     'string',  'string',     'string'],
			'SUM(t.col_decimal)' =>             ['string',       'float',    'string',     'string',  'string',     'string'],
			'MIN(t.col_decimal)' =>             ['string',       'float',    'string',     'string',  'string',     'string'],
			'MAX(t.col_decimal)' =>             ['string',       'float',    'string',     'string',  'string',     'string'],
			'SQRT(t.col_decimal)' =>            ['float',        'float',    'string',     'string',  'string',     'string'],
			'SQRT(t.col_int)' =>                ['float',        'float',    'string',     'float',   'string',     'string'],
			'SQRT(t.col_bigint)' =>             ['float',        null,       'string',     'float',       null,         null], // sqlite3 returns float, but pdo_sqlite returns NULL
			'ABS(t.col_decimal)' =>             ['string',       'float',    'string',     'string',  'string',     'string'],

			// int-ish
			'1' =>                              ['int',          'int',      'int',        'int',     'string',     'string'],
			'2147483648' =>                     ['int',          'int',      'int',        'int',     'string',     'string'],
			't.col_int' =>                      ['int',          'int',      'int',        'int',     'int',        'int'],
			't.col_bigint' =>  self::hasDbal4() ? array_fill(0, 6, 'int') : array_fill(0, 6, 'string'),
			'SUM(t.col_int)' =>                 ['string',       'int',      'int',        'int',     'string',     'string'],
			'SUM(t.col_bigint)' =>              ['string',       'int',      'string',     'string',  'string',     'string'],
			"LENGTH('')" =>                     ['int',          'int',      'int',        'int',     'int',        'int'],
			'COUNT(t)' =>                       ['int',          'int',      'int',        'int',     'int',        'int'],
			'COUNT(1)' =>                       ['int',          'int',      'int',        'int',     'int',        'int'],
			'COUNT(t.col_int)' =>               ['int',          'int',      'int',        'int',     'int',        'int'],
			'MIN(t.col_int)' =>                 ['int',          'int',      'int',        'int',     'string',     'string'],
			'MIN(t.col_bigint)' =>              ['int',          'int',      'int',        'int',     'string',     'string'],
			'MAX(t.col_int)' =>                 ['int',          'int',      'int',        'int',     'string',     'string'],
			'MAX(t.col_bigint)' =>              ['int',          'int',      'int',        'int',     'string',     'string'],
			'MOD(t.col_int, 2)' =>              ['int',          'int',      'int',        'int',     'string',     'string'],
			'MOD(t.col_bigint, 2)' =>           ['int',          'int',      'int',        'int',     'string',     'string'],
			'ABS(t.col_int)' =>                 ['int',          'int',      'int',        'int',     'string',     'string'],
			'ABS(t.col_bigint)' =>              ['int',          'int',      'int',        'int',     'string',     'string'],

			// string
			't.col_string' =>                   ['string',       'string',   'string',     'string',  'string',     'string'],
			'LOWER(t.col_string)' =>            ['string',       'string',   'string',     'string',  'string',     'string'],
			'UPPER(t.col_string)' =>            ['string',       'string',   'string',     'string',  'string',     'string'],
			'TRIM(t.col_string)' =>             ['string',       'string',   'string',     'string',  'string',     'string'],
		];

		$selects = array_keys($testData);

		$nativeMysql = array_combine($selects, array_column($testData, 0));
		$nativeSqlite = array_combine($selects, array_column($testData, 1));
		$nativePdoPg = array_combine($selects, array_column($testData, 2));
		$nativePg = array_combine($selects, array_column($testData, 3));

		$stringified = array_combine($selects, array_column($testData, 4));
		$stringifiedOldPostgre = array_combine($selects, array_column($testData, 5));

		yield 'sqlite3' => [
			'connection' => ['driver' => 'sqlite3', 'memory' => true],
			'php80-'     => $nativeSqlite,
			'php81+'     => $nativeSqlite,
			'setup'      => [],
		];

		yield 'pdo_sqlite, no stringify' => [
			'connection' => ['driver' => 'pdo_sqlite', 'memory' => true],
			'php80-'     => $stringified,
			'php81+'     => $nativeSqlite,
			'setup'      => [],
		];

		yield 'pdo_sqlite, stringify' => [
			'connection' => ['driver' => 'pdo_sqlite', 'memory' => true],
			'php80-'     => $stringified,
			'php81+'     => $stringified,
			'setup'      => [PDO::ATTR_STRINGIFY_FETCHES => true],
		];

		yield 'mysqli, no native numbers' => [
			'connection' => ['driver' => 'mysqli', 'host' => getenv('MYSQL_HOST')],
			'php80-'     => $nativeMysql,
			'php81+'     => $nativeMysql,
			'setup'      => [
				// This has no effect when using prepared statements (which is what doctrine/dbal uses)
				// - prepared statements => always native types
				// - non-prepared statements => stringified by default, can be changed by MYSQLI_OPT_INT_AND_FLOAT_NATIVE = true
				// documented here: https://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php#example-4303
				MYSQLI_OPT_INT_AND_FLOAT_NATIVE => false,
			],
		];

		yield 'mysqli, native numbers' => [
			'connection' => ['driver' => 'mysqli', 'host' => getenv('MYSQL_HOST')],
			'php80-'     => $nativeMysql,
			'php81+'     => $nativeMysql,
			'setup'      => [MYSQLI_OPT_INT_AND_FLOAT_NATIVE => true],
		];

		yield 'pdo_mysql, stringify, no emulate' => [
			'connection' => ['driver' => 'pdo_mysql', 'host' => getenv('MYSQL_HOST')],
			'php80-'     => $stringified,
			'php81+'     => $stringified,
			'setup'      => [
				PDO::ATTR_EMULATE_PREPARES => false,
				PDO::ATTR_STRINGIFY_FETCHES => true,
			],
		];

		yield 'pdo_mysql, no stringify, no emulate' => [
			'connection' => ['driver' => 'pdo_mysql', 'host' => getenv('MYSQL_HOST')],
			'php80-'     => $nativeMysql,
			'php81+'     => $nativeMysql,
			'setup'      => [PDO::ATTR_EMULATE_PREPARES => false],
		];

		yield 'pdo_mysql, no stringify, emulate' => [
			'connection' => ['driver' => 'pdo_mysql', 'host' => getenv('MYSQL_HOST')],
			'php80-'     => $stringified,
			'php81+'     => $nativeMysql,
			'setup'      => [], // defaults
		];

		yield 'pdo_mysql, stringify, emulate' => [
			'connection' => ['driver' => 'pdo_mysql', 'host' => getenv('MYSQL_HOST')],
			'php80-'     => $stringified,
			'php81+'     => $stringified,
			'setup'      => [
				PDO::ATTR_STRINGIFY_FETCHES => true,
			],
		];

		yield 'pdo_pgsql, stringify' => [
			'connection' => ['driver' => 'pdo_pgsql', 'host' => getenv('PGSQL_HOST')],

			'php80-'     => $stringifiedOldPostgre,
			'php81+'     => $stringified,
			'setup'      => [PDO::ATTR_STRINGIFY_FETCHES => true],
		];

		yield 'pdo_pgsql, no stringify' => [
			'connection' => ['driver' => 'pdo_pgsql', 'host' => getenv('PGSQL_HOST')],
			'php80-'     => $nativePdoPg,
			'php81+'     => $nativePdoPg,
			'setup'      => [],
		];

		yield 'pgsql' => [
			'connection' => ['driver' => 'pgsql', 'host' => getenv('PGSQL_HOST')],
			'php80-'     => $nativePg,
			'php81+'     => $nativePg,
			'setup'      => [],
		];
	}

	/**
	 * @param mixed $nativeConnection
	 * @param array<mixed> $attributes
	 */
	private function setupAttributes($nativeConnection, array $attributes): void
	{
		if ($nativeConnection instanceof PDO) {
			foreach ($attributes as $attribute => $value) {
				$set = $nativeConnection->setAttribute($attribute, $value);
				if (!$set) {
					throw new LogicException(sprintf('Failed to set attribute %s to %s', $attribute, $value));
				}
			}

		} elseif ($nativeConnection instanceof mysqli) {
			foreach ($attributes as $attribute => $value) {
				$set = $nativeConnection->options($attribute, $value);
				if (!$set) {
					throw new LogicException(sprintf('Failed to set attribute %s to %s', $attribute, $value));
				}
			}

		} elseif (is_a($nativeConnection, 'PgSql\Connection', true)) {
			if ($attributes !== []) {
				throw new LogicException('Cannot set attributes for PgSql\Connection driver');
			}

		} elseif ($nativeConnection instanceof SQLite3) {
			if ($attributes !== []) {
				throw new LogicException('Cannot set attributes for ' . SQLite3::class . ' driver');
			}

		} elseif (is_resource($nativeConnection)) { // e.g. `resource (pgsql link)` on PHP < 8.1 with pgsql driver
			if ($attributes !== []) {
				throw new LogicException('Cannot set attributes for this resource');
			}

		} else {
			throw new LogicException('Unexpected connection: ' . (function_exists('get_debug_type') ? get_debug_type($nativeConnection) : gettype($nativeConnection)));
		}
	}

	/**
	 * @return mixed
	 */
	private function getNativeConnection(Connection $connection)
	{
		if (method_exists($connection, 'getNativeConnection')) {
			return $connection->getNativeConnection();
		}

		if (method_exists($connection, 'getWrappedConnection')) {
			if ($connection->getWrappedConnection() instanceof PDO) {
				return $connection->getWrappedConnection();
			}

			if (method_exists($connection->getWrappedConnection(), 'getWrappedResourceHandle')) {
				return $connection->getWrappedConnection()->getWrappedResourceHandle();
			}
		}

		throw new LogicException('Unable to get native connection');
	}

	private static function hasDbal4(): bool
	{
		if (!class_exists(InstalledVersions::class)) {
			return false;
		}

		return InstalledVersions::satisfies(new VersionParser(), 'doctrine/dbal', '4.*');
	}

}
