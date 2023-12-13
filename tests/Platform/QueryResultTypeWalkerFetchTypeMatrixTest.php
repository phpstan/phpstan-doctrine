<?php declare(strict_types = 1);

namespace PHPStan\Platform;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use DateTime;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use PDO;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Php\PhpVersion;
use PHPStan\Platform\Entity\PlatformEntity;
use PHPStan\Platform\Entity\PlatformRelatedEntity;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Query\QueryResultTypeBuilder;
use PHPStan\Type\Doctrine\Query\QueryResultTypeWalker;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\UnionType;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsIdentical;
use Psr\Log\LoggerInterface;
use Throwable;
use function class_exists;
use function floor;
use function getenv;
use function in_array;
use function method_exists;
use function reset;
use function sprintf;
use function var_export;
use const PHP_VERSION_ID;

/**
 * This test ensures our query type inferring never differs from actual result types produced by PHP, Database drivers and Doctrine (with various versions and configurations).
 *
 * @group platform
 */
final class QueryResultTypeWalkerFetchTypeMatrixTest extends PHPStanTestCase
{

	private const STRINGIFY_NONE = 'none';
	private const STRINGIFY_DEFAULT = 'default';
	private const STRINGIFY_PG_BOOL = 'pg_bool';

	private const CONFIG_DEFAULT = 'default';
	private const CONFIG_STRINGIFY = 'pdo_stringify';
	private const CONFIG_NO_EMULATE = 'pdo_no_emulate';
	private const CONFIG_STRINGIFY_NO_EMULATE = 'pdo_stringify_no_emulate';

	private const INVALID_CONNECTION = 'invalid_connection';
	private const INVALID_CONNECTION_UNKNOWN_DRIVER = 'invalid_connection_and_unknown_driver';

	private const CONNECTION_CONFIGS = [
		self::CONFIG_DEFAULT => [],
		self::CONFIG_STRINGIFY => [
			PDO::ATTR_STRINGIFY_FETCHES => true,
		],
		self::CONFIG_NO_EMULATE => [
			PDO::ATTR_EMULATE_PREPARES => false,
		],
		self::CONFIG_STRINGIFY_NO_EMULATE => [
			PDO::ATTR_STRINGIFY_FETCHES => true,
			PDO::ATTR_EMULATE_PREPARES => false,
		],
	];

	public static function getAdditionalConfigFiles(): array
	{
		return [
			__DIR__ . '/data/config.neon',
		];
	}

	/**
	 * @param array<string, mixed> $data
	 * @param mixed $mysqlExpectedResult
	 * @param mixed $sqliteExpectedResult
	 * @param mixed $pdoPgsqlExpectedResult
	 * @param mixed $pgsqlExpectedResult
	 * @param mixed $mssqlExpectedResult
	 * @param self::STRINGIFY_* $stringify
	 *
	 * @dataProvider provideCases
	 */
	public function testFetchedTypes(
		array $data,
		string $dqlTemplate,
		Type $mysqlExpectedType,
		?Type $sqliteExpectedType,
		?Type $pdoPgsqlExpectedType,
		?Type $pgsqlExpectedType,
		?Type $mssqlExpectedType,
		$mysqlExpectedResult,
		$sqliteExpectedResult,
		$pdoPgsqlExpectedResult,
		$pgsqlExpectedResult,
		$mssqlExpectedResult,
		string $stringify
	): void
	{
		$dataset = (string) $this->dataName();
		$phpVersion = PHP_VERSION_ID;

		$this->performDriverTest('pdo_mysql', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $mysqlExpectedType, $mysqlExpectedResult, $stringify);
		$this->performDriverTest('pdo_mysql', self::CONFIG_STRINGIFY, $data, $dqlTemplate, $dataset, $phpVersion, $mysqlExpectedType, $mysqlExpectedResult, $stringify);
		$this->performDriverTest('pdo_mysql', self::CONFIG_NO_EMULATE, $data, $dqlTemplate, $dataset, $phpVersion, $mysqlExpectedType, $mysqlExpectedResult, $stringify);
		$this->performDriverTest('pdo_mysql', self::CONFIG_STRINGIFY_NO_EMULATE, $data, $dqlTemplate, $dataset, $phpVersion, $mysqlExpectedType, $mysqlExpectedResult, $stringify);
		$this->performDriverTest('mysqli', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $mysqlExpectedType, $mysqlExpectedResult, $stringify);

		$this->performDriverTest('pdo_sqlite', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $sqliteExpectedType, $sqliteExpectedResult, $stringify);
		$this->performDriverTest('pdo_sqlite', self::CONFIG_STRINGIFY, $data, $dqlTemplate, $dataset, $phpVersion, $sqliteExpectedType, $sqliteExpectedResult, $stringify);
		$this->performDriverTest('sqlite3', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $sqliteExpectedType, $sqliteExpectedResult, $stringify);

		$this->performDriverTest('pdo_pgsql', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $pdoPgsqlExpectedType, $pdoPgsqlExpectedResult, $stringify);
		$this->performDriverTest('pdo_pgsql', self::CONFIG_STRINGIFY, $data, $dqlTemplate, $dataset, $phpVersion, $pdoPgsqlExpectedType, $pdoPgsqlExpectedResult, $stringify);
		$this->performDriverTest('pgsql', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $pgsqlExpectedType, $pgsqlExpectedResult, $stringify);

		// unsupported driver:
		$this->performDriverTest('sqlsrv', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $mssqlExpectedType, $mssqlExpectedResult, $stringify);

		// known driver, but unknown stringification setup (connection failure)
		$this->performDriverTest('pdo_mysql', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $this->determineTypeForKnownDriverUnknownSetup($mysqlExpectedType, $stringify), $mysqlExpectedResult, $stringify, self::INVALID_CONNECTION);
		$this->performDriverTest('pdo_mysql', self::CONFIG_STRINGIFY, $data, $dqlTemplate, $dataset, $phpVersion, $this->determineTypeForKnownDriverUnknownSetup($mysqlExpectedType, $stringify), $mysqlExpectedResult, $stringify, self::INVALID_CONNECTION);

		// unknown driver, unknown setup (connection failure)
		$this->performDriverTest('pdo_mysql', self::CONFIG_DEFAULT, $data, $dqlTemplate, $dataset, $phpVersion, $this->determineTypeForUnknownDriverUnknownSetup($mysqlExpectedType, $stringify), $mysqlExpectedResult, $stringify, self::INVALID_CONNECTION_UNKNOWN_DRIVER);
		$this->performDriverTest('pdo_mysql', self::CONFIG_STRINGIFY, $data, $dqlTemplate, $dataset, $phpVersion, $this->determineTypeForUnknownDriverUnknownSetup($mysqlExpectedType, $stringify), $mysqlExpectedResult, $stringify, self::INVALID_CONNECTION_UNKNOWN_DRIVER);
	}

	/**
	 * @return iterable<string, mixed>
	 */
	public static function provideCases(): iterable
	{
		yield ' -1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT -1 FROM %s t',
			'mysql' => new ConstantIntegerType(-1),
			'sqlite' => new ConstantIntegerType(-1),
			'pdo_pgsql' => new ConstantIntegerType(-1),
			'pgsql' => new ConstantIntegerType(-1),
			'mssql' => self::mixed(),
			'mysqlResult' => -1,
			'sqliteResult' => -1,
			'pdoPgsqlResult' => -1,
			'pgsqlResult' => -1,
			'mssqlResult' => -1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 1 FROM %s t',
			'mysql' => new ConstantIntegerType(1),
			'sqlite' => new ConstantIntegerType(1),
			'pdo_pgsql' => new ConstantIntegerType(1),
			'pgsql' => new ConstantIntegerType(1),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1.0' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 1.0 FROM %s t',
			'mysql' => new ConstantStringType('1.0'),
			'sqlite' => new ConstantFloatType(1.0),
			'pdo_pgsql' => new ConstantStringType('1.0'),
			'pgsql' => new ConstantStringType('1.0'),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1.00' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 1.00 FROM %s t',
			'mysql' => new ConstantStringType('1.00'),
			'sqlite' => new ConstantFloatType(1.0),
			'pdo_pgsql' => new ConstantStringType('1.00'),
			'pgsql' => new ConstantStringType('1.00'),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00',
			'pgsqlResult' => '1.00',
			'mssqlResult' => '1.00',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 0.1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 0.1 FROM %s t',
			'mysql' => new ConstantStringType('0.1'),
			'sqlite' => new ConstantFloatType(0.1),
			'pdo_pgsql' => new ConstantStringType('0.1'),
			'pgsql' => new ConstantStringType('0.1'),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.1',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.1',
			'pgsqlResult' => '0.1',
			'mssqlResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 0.10' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 0.10 FROM %s t',
			'mysql' => new ConstantStringType('0.10'),
			'sqlite' => new ConstantFloatType(0.1),
			'pdo_pgsql' => new ConstantStringType('0.10'),
			'pgsql' => new ConstantStringType('0.10'),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.10',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.10',
			'pgsqlResult' => '0.10',
			'mssqlResult' => '.10',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '0.125e0' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 0.125e0 FROM %s t',
			'mysql' => new ConstantFloatType(0.125),
			'sqlite' => new ConstantFloatType(0.125),
			'pdo_pgsql' => new ConstantStringType('0.125'),
			'pgsql' => new ConstantStringType('0.125'),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => '0.125',
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1e0' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 1e0 FROM %s t',
			'mysql' => new ConstantFloatType(1.0),
			'sqlite' => new ConstantFloatType(1.0),
			'pdo_pgsql' => new ConstantStringType('1'),
			'pgsql' => new ConstantStringType('1'),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => '1',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield " '1'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT '1' FROM %s t",
			'mysql' => new ConstantStringType('1'),
			'sqlite' => new ConstantStringType('1'),
			'pdo_pgsql' => new ConstantStringType('1'),
			'pgsql' => new ConstantStringType('1'),
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => '1',
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => '1',
			'mssqlResult' => '1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield " '1e0'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT '1e0' FROM %s t",
			'mysql' => new ConstantStringType('1e0'),
			'sqlite' => new ConstantStringType('1e0'),
			'pdo_pgsql' => new ConstantStringType('1e0'),
			'pgsql' => new ConstantStringType('1e0'),
			'mssql' => self::mixed(),
			'mysqlResult' => '1e0',
			'sqliteResult' => '1e0',
			'pdoPgsqlResult' => '1e0',
			'pgsqlResult' => '1e0',
			'mssqlResult' => '1e0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 + 1) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2,
			'sqliteResult' => 2,
			'pdoPgsqlResult' => 2,
			'pgsqlResult' => 2,
			'mssqlResult' => 2,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + 'foo'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 + 'foo') FROM %s t",
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + '1.0'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 + '1.0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => 2.0,
			'sqliteResult' => 2.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + '1'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 + '1') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2.0,
			'sqliteResult' => 2,
			'pdoPgsqlResult' => 2,
			'pgsqlResult' => 2,
			'mssqlResult' => 2,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + '1e0'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 + '1e0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => 2.0,
			'sqliteResult' => 2.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + 1 * 1 - 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 + 1 * 1 - 1) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + 1 * 1 / 1 - 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 + 1 * 1 / 1 - 1) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_int' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_int FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 18,
			'sqliteResult' => 18,
			'pdoPgsqlResult' => 18,
			'pgsqlResult' => 18,
			'mssqlResult' => 18,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint + t.col_bigint' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bigint + t.col_bigint FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 4294967296,
			'sqliteResult' => 4294967296,
			'pdoPgsqlResult' => 4294967296,
			'pgsqlResult' => 4294967296,
			'mssqlResult' => '4294967296',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 9.125,
			'sqliteResult' => 9.125,
			'pdoPgsqlResult' => '9.125',
			'pgsqlResult' => 9.125,
			'mssqlResult' => 9.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_mixed' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_mixed FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 10,
			'sqliteResult' => 10,
			'pdoPgsqlResult' => 10,
			'pgsqlResult' => 10,
			'mssqlResult' => 10,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint + t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bigint + t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2147483648.125,
			'sqliteResult' => 2147483648.125,
			'pdoPgsqlResult' => '2147483648.125',
			'pgsqlResult' => 2147483648.125,
			'mssqlResult' => 2147483648.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint + t.col_float (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_bigint + t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2.0,
			'sqliteResult' => 2.0,
			'pdoPgsqlResult' => '2',
			'pgsqlResult' => 2.0,
			'mssqlResult' => 2.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float + t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float + t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.25,
			'sqliteResult' => 0.25,
			'pdoPgsqlResult' => '0.25',
			'pgsqlResult' => 0.25,
			'mssqlResult' => 0.25,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '9.1',
			'sqliteResult' => 9.1,
			'pdoPgsqlResult' => '9.1',
			'pgsqlResult' => '9.1',
			'mssqlResult' => '9.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_int + t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '2.0',
			'sqliteResult' => 2,
			'pdoPgsqlResult' => '2.0',
			'pgsqlResult' => '2.0',
			'mssqlResult' => '2.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float + t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float + t.col_decimal FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.225,
			'sqliteResult' => 0.225,
			'pdoPgsqlResult' => '0.225',
			'pgsqlResult' => 0.225,
			'mssqlResult' => 0.225,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float + t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_float + t.col_decimal FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2.0,
			'sqliteResult' => 2.0,
			'pdoPgsqlResult' => '2',
			'pgsqlResult' => 2.0,
			'mssqlResult' => 2.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal + t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_decimal + t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '2.0',
			'sqliteResult' => 2,
			'pdoPgsqlResult' => '2.0',
			'pgsqlResult' => '2.0',
			'mssqlResult' => '2.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_float + t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_float + t.col_decimal FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 9.225,
			'sqliteResult' => 9.225,
			'pdoPgsqlResult' => '9.225',
			'pgsqlResult' => 9.225,
			'mssqlResult' => 9.225,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal + t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal + t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.2',
			'sqliteResult' => 0.2,
			'pdoPgsqlResult' => '0.2',
			'pgsqlResult' => '0.2',
			'mssqlResult' => '.2',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_string' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => 9.0,
			'sqliteResult' => 9,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_string (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_int + t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => 2.0,
			'sqliteResult' => 2,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 2,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_bool' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_bool FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null,
			'mssql' => self::mixed(), // Undefined function
			'mysqlResult' => 10,
			'sqliteResult' => 10,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 10,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float + t.col_string' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float + t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal + t.col_bool' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal + t.col_bool FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => '1.1',
			'sqliteResult' => 1.1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => '1.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal + t.col_string' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal + t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => 0.1,
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_int_nullable' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int + t.col_int_nullable FROM %s t',
			'mysql' => self::intOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => self::intOrNull(),
			'pgsql' => self::intOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_int' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_int FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint / t.col_bigint' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bigint / t.col_bigint FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => '1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 72.0,
			'sqliteResult' => 72.0,
			'pdoPgsqlResult' => '72',
			'pgsqlResult' => 72.0,
			'mssqlResult' => 72.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_float / t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_float / t.col_decimal FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 720.0,
			'sqliteResult' => 720.0,
			'pdoPgsqlResult' => '720',
			'pgsqlResult' => 720.0,
			'mssqlResult' => 720.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint / t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bigint / t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 17179869184.0,
			'sqliteResult' => 17179869184.0,
			'pdoPgsqlResult' => '17179869184',
			'pgsqlResult' => 17179869184.0,
			'mssqlResult' => 17179869184.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float / t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float / t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '90.0000',
			'sqliteResult' => 90.0,
			'pdoPgsqlResult' => '90.0000000000000000',
			'pgsqlResult' => '90.0000000000000000',
			'mssqlResult' => '90.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_int / t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float / t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float / t.col_decimal FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.25,
			'sqliteResult' => 1.25,
			'pdoPgsqlResult' => '1.25',
			'pgsqlResult' => 1.25,
			'mssqlResult' => 1.25,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal / t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_decimal / t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_mixed' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal / t.col_mixed FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.10000',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.10000000000000000000',
			'pgsqlResult' => '0.10000000000000000000',
			'mssqlResult' => '.100000000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_string' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Conversion failed
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_string (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_int / t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_string / t.col_int' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_string / t.col_int FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Conversion failed
			'mysqlResult' => 0.0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_bool' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_bool FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::int(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => '9.0000',
			'sqliteResult' => 9,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float / t.col_string' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float / t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_string / t.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_string / t.col_float FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_bool' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal / t.col_bool FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => '0.10000',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => '.100000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_bool (int data)' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT t.col_decimal / t.col_bool FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_string' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal / t.col_string FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_string / t.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_string / t.col_decimal FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_int_nullable' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int / t.col_int_nullable FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => self::intOrNull(),
			'pgsql' => self::intOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 - 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 - 1) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 * 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 * 1) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 * '1'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 * '1') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 * '1.0'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 * '1.0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 / 1' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 / 1) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 / 1.0' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 / 1.0) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 / 1e0' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (1 / 1e0) FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "'foo' / 1" => [
			'data' => self::dataDefault(),
			'select' => "SELECT ('foo' / 1) FROM %s t",
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => 0.0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 / 'foo'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 / 'foo') FROM %s t",
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 / '1'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 / '1') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "'1' / 1" => [
			'data' => self::dataDefault(),
			'select' => "SELECT ('1' / 1) FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 / '1.0'" => [
			'data' => self::dataDefault(),
			'select' => "SELECT (1 / '1.0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Conversion failed
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '2147483648 ' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT 2147483648 FROM %s t',
			'mysql' => new ConstantIntegerType(2147483648),
			'sqlite' => new ConstantIntegerType(2147483648),
			'pdo_pgsql' => new ConstantIntegerType(2147483648),
			'pgsql' => new ConstantIntegerType(2147483648),
			'mssql' => self::mixed(),
			'mysqlResult' => 2147483648,
			'sqliteResult' => 2147483648,
			'pdoPgsqlResult' => 2147483648,
			'pgsqlResult' => 2147483648,
			'mssqlResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "''" => [
			'data' => self::dataDefault(),
			'select' => 'SELECT \'\' FROM %s t',
			'mysql' => new ConstantStringType(''),
			'sqlite' => new ConstantStringType(''),
			'pdo_pgsql' => new ConstantStringType(''),
			'pgsql' => new ConstantStringType(''),
			'mssql' => self::mixed(),
			'mysqlResult' => '',
			'sqliteResult' => '',
			'pdoPgsqlResult' => '',
			'pgsqlResult' => '',
			'mssqlResult' => '',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '(TRUE)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (TRUE) FROM %s t',
			'mysql' => new ConstantIntegerType(1),
			'sqlite' => new ConstantIntegerType(1),
			'pdo_pgsql' => new ConstantBooleanType(true),
			'pgsql' => new ConstantBooleanType(true),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => true,
			'pgsqlResult' => true,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_PG_BOOL,
		];

		yield '(FALSE)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT (FALSE) FROM %s t',
			'mysql' => new ConstantIntegerType(0),
			'sqlite' => new ConstantIntegerType(0),
			'pdo_pgsql' => new ConstantBooleanType(false),
			'pgsql' => new ConstantBooleanType(false),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => false,
			'pgsqlResult' => false,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_PG_BOOL,
		];

		yield 't.col_bool' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bool FROM %s t',
			'mysql' => self::bool(),
			'sqlite' => self::bool(),
			'pdo_pgsql' => self::bool(),
			'pgsql' => self::bool(),
			'mssql' => self::bool(),
			'mysqlResult' => true,
			'sqliteResult' => true,
			'pdoPgsqlResult' => true,
			'pgsqlResult' => true,
			'mssqlResult' => true,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_bool_nullable' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bool_nullable FROM %s t',
			'mysql' => self::boolOrNull(),
			'sqlite' => self::boolOrNull(),
			'pdo_pgsql' => self::boolOrNull(),
			'pgsql' => self::boolOrNull(),
			'mssql' => self::boolOrNull(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COALESCE(t.col_bool, t.col_bool)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_bool, t.col_bool) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::bool(),
			'pgsql' => self::bool(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => true,
			'pgsqlResult' => true,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_PG_BOOL,
		];

		yield 'COALESCE(t.col_decimal, t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT COALESCE(t.col_decimal, t.col_decimal) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_float, t.col_float) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT COALESCE(t.col_float, t.col_float) FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_decimal FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::numericString(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::numericString(),
			'mysqlResult' => '0.1',
			'sqliteResult' => '0.1',
			'pdoPgsqlResult' => '0.1',
			'pgsqlResult' => '0.1',
			'mssqlResult' => '.1',
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_int' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_int FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::int(),
			'mysqlResult' => 9,
			'sqliteResult' => 9,
			'pdoPgsqlResult' => 9,
			'pgsqlResult' => 9,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_bigint' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_bigint FROM %s t',
			'mysql' => self::hasDbal4() ? self::int() : self::numericString(),
			'sqlite' => self::hasDbal4() ? self::int() : self::numericString(),
			'pdo_pgsql' => self::hasDbal4() ? self::int() : self::numericString(),
			'pgsql' => self::hasDbal4() ? self::int() : self::numericString(),
			'mssql' => self::hasDbal4() ? self::int() : self::numericString(),
			'mysqlResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'sqliteResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'pdoPgsqlResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'pgsqlResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'mssqlResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_float' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_float FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::float(),
			'pgsql' => self::float(),
			'mssql' => self::float(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => 0.125,
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'AVG(t.col_float)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_float) + no data' => [
			'data' => self::dataNone(),
			'select' => 'SELECT AVG(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_float) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_float) FROM %s t GROUP BY t.col_int',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_float_nullable) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_float_nullable) FROM %s t GROUP BY t.col_int',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_decimal) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.10000',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.10000000000000000000',
			'pgsqlResult' => '0.10000000000000000000',
			'mssqlResult' => '.100000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT AVG(t.col_decimal) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_mixed) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::floatOrNull(), // always float|null, see https://www.sqlite.org/lang_aggfunc.html
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_int) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '9.0000',
			'sqliteResult' => 9.0,
			'pdoPgsqlResult' => '9.0000000000000000',
			'pgsqlResult' => '9.0000000000000000',
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_bool)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_bool) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // perand data type bit is invalid for avg operator.
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_string) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type nvarchar is invalid for avg operator
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(1) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('1')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT AVG('1') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('1.0')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT AVG('1.0') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('1e0')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT AVG('1e0') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('foo')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT AVG('foo') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(1) FROM %s t GROUP BY t.col_int',
			'mysql' => self::numericString(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1.0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(1.0) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1e0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(1.0) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.00000',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.00000000000000000000',
			'pgsqlResult' => '1.00000000000000000000',
			'mssqlResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT AVG(t.col_bigint) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '2147483648.0000',
			'sqliteResult' => 2147483648.0,
			'pdoPgsqlResult' => '2147483648.00000000',
			'pgsqlResult' => '2147483648.00000000',
			'mssqlResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_float)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_float) + no data' => [
			'data' => self::dataNone(),
			'select' => 'SELECT SUM(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_float) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_float) FROM %s t GROUP BY t.col_int',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + -(CASE WHEN MIN(t.col_float) = 0 THEN SUM(t.col_float) ELSE 0 END)' => [ // agg function (causing null) deeply inside AST
			'data' => self::dataDefault(),
			'select' => 'SELECT 1 + -(CASE WHEN MIN(t.col_float) = 0 THEN SUM(t.col_float) ELSE 0 END) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrIntOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_decimal) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrIntOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.1',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.1',
			'pgsqlResult' => '0.1',
			'mssqlResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT SUM(t.col_decimal) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrIntOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_int) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'mssql' => self::mixed(),
			'mysqlResult' => '9',
			'sqliteResult' => 9,
			'pdoPgsqlResult' => 9,
			'pgsqlResult' => 9,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '-SUM(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT -SUM(t.col_int) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'mssql' => self::mixed(),
			'mysqlResult' => '-9',
			'sqliteResult' => -9,
			'pdoPgsqlResult' => -9,
			'pgsqlResult' => -9,
			'mssqlResult' => -9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '-SUM(t.col_int) + no data' => [
			'data' => self::dataNone(),
			'select' => 'SELECT -SUM(t.col_int) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_mixed) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_bool)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_bool) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => '1',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_string) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('foo')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT SUM('foo') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('1')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT SUM('1') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1.0,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('1.0')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT SUM('1.0') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('1.1')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT SUM('1.1') FROM %s t",
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1.1,
			'sqliteResult' => 1.1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(1) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(1) FROM %s t GROUP BY t.col_int',
			'mysql' => self::numericString(),
			'sqlite' => self::int(),
			'pdo_pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1.0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(1.0) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1e0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(1e0) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => '1',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT SUM(t.col_bigint) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'pgsql' => TypeCombinator::union(self::numericStringOrNull(), self::intOrNull()),
			'mssql' => self::mixed(),
			'mysqlResult' => '2147483648',
			'sqliteResult' => 2147483648,
			'pdoPgsqlResult' => '2147483648',
			'pgsqlResult' => '2147483648',
			'mssqlResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_float)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_float) + no data' => [
			'data' => self::dataNone(),
			'select' => 'SELECT MAX(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_float) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_float) FROM %s t GROUP BY t.col_int',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_decimal) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrIntOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.1',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.1',
			'pgsqlResult' => '0.1',
			'mssqlResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT MAX(t.col_decimal) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrIntOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_int) FROM %s t',
			'mysql' => self::intOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => self::intOrNull(),
			'pgsql' => self::intOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 9,
			'sqliteResult' => 9,
			'pdoPgsqlResult' => 9,
			'pgsqlResult' => 9,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_mixed) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_bool)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_bool) FROM %s t',
			'mysql' => self::intOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_string) FROM %s t',
			'mysql' => self::stringOrNull(),
			'sqlite' => self::stringOrNull(),
			'pdo_pgsql' => self::stringOrNull(),
			'pgsql' => self::stringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 'foobar',
			'sqliteResult' => 'foobar',
			'pdoPgsqlResult' => 'foobar',
			'pgsqlResult' => 'foobar',
			'mssqlResult' => 'foobar',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MAX('foobar')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT MAX('foobar') FROM %s t",
			'mysql' => TypeCombinator::addNull(self::string()),
			'sqlite' => TypeCombinator::addNull(self::string()),
			'pdo_pgsql' => TypeCombinator::addNull(self::string()),
			'pgsql' => TypeCombinator::addNull(self::string()),
			'mssql' => self::mixed(),
			'mysqlResult' => 'foobar',
			'sqliteResult' => 'foobar',
			'pdoPgsqlResult' => 'foobar',
			'pgsqlResult' => 'foobar',
			'mssqlResult' => 'foobar',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MAX('1')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT MAX('1') FROM %s t",
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::numericStringOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => '1',
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => '1',
			'mssqlResult' => '1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MAX('1.0')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT MAX('1.0') FROM %s t",
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::numericStringOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => '1.0',
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(1) FROM %s t',
			'mysql' => self::intOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => self::intOrNull(),
			'pgsql' => self::intOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1) + GROUP BY' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(1) FROM %s t GROUP BY t.col_int',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1.0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(1.0) FROM %s t',
			'mysql' => self::numericStringOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1e0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(1e0) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::numericStringOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => '1',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MAX(t.col_bigint) FROM %s t',
			'mysql' => self::intOrNull(),
			'sqlite' => self::intOrNull(),
			'pdo_pgsql' => self::intOrNull(),
			'pgsql' => self::intOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2147483648,
			'sqliteResult' => 2147483648,
			'pdoPgsqlResult' => 2147483648,
			'pgsqlResult' => 2147483648,
			'mssqlResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_float)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_float) FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.125,
			'pdoPgsqlResult' => '0.125',
			'pgsqlResult' => 0.125,
			'mssqlResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_decimal) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.1',
			'sqliteResult' => 0.1,
			'pdoPgsqlResult' => '0.1',
			'pgsqlResult' => '0.1',
			'mssqlResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT ABS(t.col_decimal) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::floatOrInt(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_int) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 9,
			'sqliteResult' => 9,
			'pdoPgsqlResult' => 9,
			'pgsqlResult' => 9,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '-ABS(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT -ABS(t.col_int) FROM %s t',
			'mysql' => IntegerRangeType::fromInterval(null, 0),
			'sqlite' => IntegerRangeType::fromInterval(null, 0),
			'pdo_pgsql' => IntegerRangeType::fromInterval(null, 0),
			'pgsql' => IntegerRangeType::fromInterval(null, 0),
			'mssql' => self::mixed(),
			'mysqlResult' => -9,
			'sqliteResult' => -9,
			'pdoPgsqlResult' => -9,
			'pgsqlResult' => -9,
			'mssqlResult' => -9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_int_nullable)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_int_nullable) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => self::intNonNegativeOrNull(),
			'pgsql' => self::intNonNegativeOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_string) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // Operand data type is invalid
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_string) + int data' => [
			'data' => self::dataAllIntLike(),
			'select' => 'SELECT ABS(t.col_string) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_bool)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_bool) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(-1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(-1) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(1) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(1.0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(1.0) FROM %s t',
			'mysql' => self::numericString(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => '1.0',
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.0',
			'pgsqlResult' => '1.0',
			'mssqlResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(1e0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(1e0) FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => '1',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "ABS('1.0')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT ABS('1.0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "ABS('1')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT ABS('1') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_bigint) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2147483648,
			'sqliteResult' => 2147483648,
			'pdoPgsqlResult' => 2147483648,
			'pgsqlResult' => 2147483648,
			'mssqlResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT ABS(t.col_mixed) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_int, 0) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => null,
			'pgsql' => null,
			'mssql' => null, // Divide by zero error encountered.
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, 1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_int, 1) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_mixed, 1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_mixed, 1) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MOD(t.col_int, '1')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT MOD(t.col_int, '1') FROM %s t",
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MOD(t.col_int, '1.0')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT MOD(t.col_int, '1') FROM %s t",
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_float)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_int, t.col_float) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // The data types are incompatible in the modulo operator.
			'mysqlResult' => 0.0,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_decimal)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_int, t.col_decimal) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.0',
			'sqliteResult' => null,
			'pdoPgsqlResult' => '0.0',
			'pgsqlResult' => '0.0',
			'mssqlResult' => '.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_float, t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_float, t.col_int) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // The data types are incompatible in the modulo operator.
			'mysqlResult' => 0.125,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_decimal, t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_decimal, t.col_int) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.1',
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => '0.1',
			'pgsqlResult' => '0.1',
			'mssqlResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_string, t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_string, t.col_string) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => null, // Undefined function
			'pgsql' => null, // Undefined function
			'mssql' => null, // The data types are incompatible in the modulo operator.
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_int, t.col_int) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_int_nullable)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_int, t.col_int_nullable) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => self::intNonNegativeOrNull(),
			'pgsql' => self::intNonNegativeOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(10, 7)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(10, 7) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 3,
			'sqliteResult' => 3,
			'pdoPgsqlResult' => 3,
			'pgsqlResult' => 3,
			'mssqlResult' => 3,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(10, -7)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(10, -7) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 3,
			'sqliteResult' => 3,
			'pdoPgsqlResult' => 3,
			'pgsqlResult' => 3,
			'mssqlResult' => 3,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_bigint, t.col_bigint)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT MOD(t.col_bigint, t.col_bigint) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => '0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_bigint, t.col_bigint)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BIT_AND(t.col_bigint, t.col_bigint) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 2147483648,
			'sqliteResult' => 2147483648,
			'pdoPgsqlResult' => 2147483648,
			'pgsqlResult' => 2147483648,
			'mssqlResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_int, t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BIT_AND(t.col_int, t.col_int) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 9,
			'sqliteResult' => 9,
			'pdoPgsqlResult' => 9,
			'pgsqlResult' => 9,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_mixed, t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BIT_AND(t.col_mixed, t.col_mixed) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => self::intNonNegativeOrNull(),
			'pgsql' => self::intNonNegativeOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_int, t.col_int_nullable)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BIT_AND(t.col_int, t.col_int_nullable) FROM %s t',
			'mysql' => self::intNonNegativeOrNull(),
			'sqlite' => self::intNonNegativeOrNull(),
			'pdo_pgsql' => self::intNonNegativeOrNull(),
			'pgsql' => self::intNonNegativeOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(1, 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BIT_AND(1, 0) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_string, t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BIT_AND(t.col_string, t.col_string) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => null,
			'pgsql' => null,
			'mssql' => null,
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'DATE_DIFF(CURRENT_DATE(), CURRENT_DATE())' => [
			'data' => self::dataDefault(),
			'select' => "SELECT DATE_DIFF('2024-01-01 12:00', '2024-01-01 11:00') FROM %s t",
			'mysql' => self::int(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'DATE_DIFF(CURRENT_DATE(), t.col_string_nullable)' => [
			'data' => self::dataDefault(),
			'select' => "SELECT DATE_DIFF('2024-01-01 12:00', t.col_string_nullable) FROM %s t",
			'mysql' => self::intOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::intOrNull(),
			'pgsql' => self::intOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'DATE_DIFF(CURRENT_DATE(), t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => "SELECT DATE_DIFF('2024-01-01 12:00', t.col_mixed) FROM %s t",
			'mysql' => self::intOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null,
			'pgsql' => null,
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => 2460310.0,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 45289,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_float)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(t.col_float) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_decimal)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(t.col_decimal) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.000000000000000',
			'pgsqlResult' => '1.000000000000000',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_int)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(t.col_int) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 3.0,
			'sqliteResult' => 3.0,
			'pdoPgsqlResult' => '3',
			'pgsqlResult' => 3.0,
			'mssqlResult' => 3.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_mixed)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(t.col_mixed) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_int_nullable)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(t.col_int_nullable) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => PHP_VERSION_ID >= 80100 && !self::hasDbal4() ? null : self::floatOrNull(), // fails in UDF since PHP 8.1: sqrt(): Passing null to parameter #1 ($num) of type float is deprecated
			'pdo_pgsql' => self::numericStringOrNull(),
			'pgsql' => self::floatOrNull(),
			'mssql' => self::mixed(),
			'mysqlResult' => null,
			'sqliteResult' => self::hasDbal4() ? null : 0.0, // 0.0 caused by UDF wired through PHP's sqrt() which returns 0.0 for null
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(-1)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(-1) FROM %s t',
			'mysql' => self::floatOrNull(),
			'sqlite' => self::floatOrNull(),
			'pdo_pgsql' => null, // failure: cannot take square root of a negative number
			'pgsql' => null, // failure: cannot take square root of a negative number
			'mssql' => null, // An invalid floating point operation occurred.
			'mysqlResult' => null,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(1)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(1) FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SQRT('1')" => [
			'data' => self::dataSqrt(),
			'select' => "SELECT SQRT('1') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SQRT('1.0')" => [
			'data' => self::dataSqrt(),
			'select' => "SELECT SQRT('1.0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SQRT('1e0')" => [
			'data' => self::dataSqrt(),
			'select' => "SELECT SQRT('1e0') FROM %s t",
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::float(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1',
			'pgsqlResult' => 1.0,
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SQRT('foo')" => [
			'data' => self::dataSqrt(),
			'select' => "SELECT SQRT('foo') FROM %s t",
			'mysql' => self::mixed(),
			'sqlite' => self::hasDbal4() ? self::mixed() : null, // fails in UDF: sqrt(): Argument #1 ($num) must be of type float, string given
			'pdo_pgsql' => null, // Invalid text representation
			'pgsql' => null, // Invalid text representation
			'mssql' => null, // Error converting data type
			'mysqlResult' => 0.0,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_string)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(t.col_string) FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::hasDbal4() ? self::mixed() : null, // fails in UDF: sqrt(): Argument #1 ($num) must be of type float, string given
			'pdo_pgsql' => null, // undefined function
			'pgsql' => null, // undefined function
			'mssql' => null, // Error converting data type
			'mysqlResult' => 0.0,
			'sqliteResult' => null,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(1.0)' => [
			'data' => self::dataSqrt(),
			'select' => 'SELECT SQRT(1.0) FROM %s t',
			'mysql' => self::float(),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => self::numericString(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1.0,
			'sqliteResult' => 1.0,
			'pdoPgsqlResult' => '1.000000000000000',
			'pgsqlResult' => '1.000000000000000',
			'mssqlResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COUNT(t)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COUNT(t) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::intNonNegative(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COUNT(t.col_int)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COUNT(t.col_int) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::intNonNegative(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COUNT(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COUNT(t.col_mixed) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::intNonNegative(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COUNT(1)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COUNT(1) FROM %s t',
			'mysql' => self::intNonNegative(),
			'sqlite' => self::intNonNegative(),
			'pdo_pgsql' => self::intNonNegative(),
			'pgsql' => self::intNonNegative(),
			'mssql' => self::intNonNegative(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_mixed' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT t.col_mixed FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'INT_PI()' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT INT_PI() FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::int(),
			'mysqlResult' => 3,
			'sqliteResult' => 3,
			'pdoPgsqlResult' => 3,
			'pgsqlResult' => 3,
			'mssqlResult' => 3,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'BOOL_PI()' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT BOOL_PI() FROM %s t',
			'mysql' => self::bool(),
			'sqlite' => self::bool(),
			'pdo_pgsql' => self::bool(),
			'pgsql' => self::bool(),
			'mssql' => self::bool(),
			'mysqlResult' => true,
			'sqliteResult' => true,
			'pdoPgsqlResult' => true,
			'pgsqlResult' => true,
			'mssqlResult' => true,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'STRING_PI()' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT STRING_PI() FROM %s t',
			'mysql' => self::mixed(),
			'sqlite' => self::mixed(),
			'pdo_pgsql' => self::mixed(),
			'pgsql' => self::mixed(),
			'mssql' => self::mixed(),
			'mysqlResult' => '3.14159',
			'sqliteResult' => 3.14159,
			'pdoPgsqlResult' => '3.14159',
			'pgsqlResult' => '3.14159',
			'mssqlResult' => '3.14159',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_datetime, t.col_datetime)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_datetime, t.col_datetime) FROM %s t',
			'mysql' => self::string(),
			'sqlite' => self::string(),
			'pdo_pgsql' => self::string(),
			'pgsql' => self::string(),
			'mssql' => self::mixed(),
			'mysqlResult' => '2024-01-31 12:59:59',
			'sqliteResult' => '2024-01-31 12:59:59',
			'pdoPgsqlResult' => '2024-01-31 12:59:59',
			'pgsqlResult' => '2024-01-31 12:59:59',
			'mssqlResult' => '2024-01-31 12:59:59.000000', // doctrine/dbal changes default ReturnDatesAsStrings to true
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(SUM(t.col_int_nullable), 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(SUM(t.col_int_nullable), 0) FROM %s t',
			'mysql' => TypeCombinator::union(self::numericString(), self::int()),
			'sqlite' => self::int(),
			'pdo_pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'mssql' => self::mixed(),
			'mysqlResult' => '0',
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(SUM(ABS(t.col_int)), 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(SUM(ABS(t.col_int)), 0) FROM %s t',
			'mysql' => TypeCombinator::union(self::int(), self::numericString()),
			'sqlite' => self::int(),
			'pdo_pgsql' => TypeCombinator::union(self::int(), self::numericString()),
			'pgsql' => TypeCombinator::union(self::int(), self::numericString()),
			'mssql' => self::mixed(),
			'mysqlResult' => '9',
			'sqliteResult' => 9,
			'pdoPgsqlResult' => 9,
			'pgsqlResult' => 9,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_int_nullable, 'foo')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT COALESCE(t.col_int_nullable, 'foo') FROM %s t",
			'mysql' => TypeCombinator::union(self::int(), self::string()),
			'sqlite' => TypeCombinator::union(self::int(), self::string()),
			'pdo_pgsql' => null, // COALESCE types cannot be matched
			'pgsql' => null, // COALESCE types cannot be matched
			'mssql' => null, // Conversion failed
			'mysqlResult' => 'foo',
			'sqliteResult' => 'foo',
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_int, 'foo')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT COALESCE(t.col_int, 'foo') FROM %s t",
			'mysql' => TypeCombinator::union(self::int(), self::string()),
			'sqlite' => TypeCombinator::union(self::int(), self::string()),
			'pdo_pgsql' => null, // COALESCE types cannot be matched
			'pgsql' => null, // COALESCE types cannot be matched
			'mssql' => self::mixed(),
			'mysqlResult' => '9',
			'sqliteResult' => 9,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_bool, 'foo')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT COALESCE(t.col_bool, 'foo') FROM %s t",
			'mysql' => TypeCombinator::union(self::int(), self::string()),
			'sqlite' => TypeCombinator::union(self::int(), self::string()),
			'pdo_pgsql' => null, // COALESCE types cannot be matched
			'pgsql' => null, // COALESCE types cannot be matched
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(1, 'foo')" => [
			'data' => self::dataDefault(),
			'select' => "SELECT COALESCE(1, 'foo') FROM %s t",
			'mysql' => TypeCombinator::union(self::int(), self::string()),
			'sqlite' => TypeCombinator::union(self::int(), self::string()),
			'pdo_pgsql' => null, // COALESCE types cannot be matched
			'pgsql' => null, // COALESCE types cannot be matched
			'mssql' => self::mixed(),
			'mysqlResult' => '1',
			'sqliteResult' => 1,
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_int_nullable, 0) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => 0,
			'pgsqlResult' => 0,
			'mssqlResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_float_nullable, 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_float_nullable, 0) FROM %s t',
			'mysql' => TypeCombinator::union(self::float(), self::int()),
			'sqlite' => TypeCombinator::union(self::float(), self::int()),
			'pdo_pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'pgsql' => TypeCombinator::union(self::float(), self::int()),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => '0',
			'pgsqlResult' => 0.0,
			'mssqlResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_float_nullable, 0.0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_float_nullable, 0.0) FROM %s t',
			'mysql' => TypeCombinator::union(self::float(), self::numericString()),
			'sqlite' => self::float(),
			'pdo_pgsql' => self::numericString(),
			'pgsql' => TypeCombinator::union(self::float(), self::numericString()),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.0,
			'sqliteResult' => 0.0,
			'pdoPgsqlResult' => '0',
			'pgsqlResult' => 0.0,
			'mssqlResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, 0) FROM %s t',
			'mysql' => TypeCombinator::union(self::numericString(), self::int()),
			'sqlite' => TypeCombinator::union(self::float(), self::int()),
			'pdo_pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'mssql' => self::mixed(),
			'mysqlResult' => '0.0',
			'sqliteResult' => 0,
			'pdoPgsqlResult' => '0',
			'pgsqlResult' => '0',
			'mssqlResult' => '.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0) FROM %s t',
			'mysql' => TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'sqlite' => TypeCombinator::union(self::float(), self::int()),
			'pdo_pgsql' => TypeCombinator::union(self::numericString(), self::int()),
			'pgsql' => TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'mssql' => self::mixed(),
			'mysqlResult' => 0.0,
			'sqliteResult' => 0,
			'pdoPgsqlResult' => '0',
			'pgsqlResult' => 0.0,
			'mssqlResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, t.col_string)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, t.col_string) FROM %s t',
			'mysql' => TypeCombinator::union(self::string(), self::int(), self::float()),
			'sqlite' => TypeCombinator::union(self::float(), self::int(), self::string()),
			'pdo_pgsql' => null, // COALESCE types cannot be matched
			'pgsql' => null, // COALESCE types cannot be matched
			'mssql' => null, // Error converting data
			'mysqlResult' => 'foobar',
			'sqliteResult' => 'foobar',
			'pdoPgsqlResult' => null,
			'pgsqlResult' => null,
			'mssqlResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'IDENTITY(t.related_entity)' => [
			'data' => self::dataDefault(),
			'select' => 'SELECT IDENTITY(t.related_entity) FROM %s t',
			'mysql' => self::int(),
			'sqlite' => self::int(),
			'pdo_pgsql' => self::int(),
			'pgsql' => self::int(),
			'mssql' => self::mixed(),
			'mysqlResult' => 1,
			'sqliteResult' => 1,
			'pdoPgsqlResult' => 1,
			'pgsqlResult' => 1,
			'mssqlResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];
	}

	/**
	 * @param mixed $expectedFirstResult
	 * @param array<string, mixed> $data
	 * @param self::STRINGIFY_* $stringification
	 * @param self::INVALID_*|null $invalidConnectionSetup
	 */
	private function performDriverTest(
		string $driver,
		string $configName,
		array $data,
		string $dqlTemplate,
		string $dataset,
		int $phpVersion,
		?Type $expectedInferredType,
		$expectedFirstResult,
		string $stringification,
		?string $invalidConnectionSetup = null
	): void
	{
		$connectionParams = [
			'driver' => $driver,
			'driverOptions' => self::CONNECTION_CONFIGS[$configName],
		] + $this->getConnectionParamsForDriver($driver);

		$dql = sprintf($dqlTemplate, PlatformEntity::class);

		$connection = $this->createConnection($connectionParams);
		$query = $this->getQuery($connection, $dql, $data);
		$sql = $query->getSQL();

		self::assertIsString($sql);

		try {
			$result = $query->getSingleResult();
			$realResultType = ConstantTypeHelper::getTypeFromValue($result);

			if ($invalidConnectionSetup !== null) {
				$inferredType = $this->getInferredType($this->cloneQueryAndInjectInvalidConnection($query, $driver, $invalidConnectionSetup), false);
			} else {
				$inferredType = $this->getInferredType($query, true);
			}

		} catch (Throwable $e) {
			if ($expectedInferredType === null) {
				return;
			}
			throw $e;
		} finally {
			$connection->close();
		}

		if ($expectedInferredType === null) {
			self::fail(sprintf(
				"Expected failure, but none occurred\n\nDriver: %s\nConfig: %s\nDataset: %s\nDQL: %s\nSQL: %s\nReal result: %s\nInferred type: %s\n",
				$driver,
				$configName,
				$dataset,
				$dql,
				$sql,
				$realResultType->describe(VerbosityLevel::precise()),
				$inferredType->describe(VerbosityLevel::precise())
			));
		}

		$driverDetector = new DriverDetector(true);
		$driverType = $driverDetector->detect($query->getEntityManager()->getConnection());

		$stringify = $this->shouldStringify($stringification, $driverType, $phpVersion, $configName);
		if (
			$stringify
			&& $invalidConnectionSetup === null // do not stringify, we already passed union with stringified one above
		) {
			$expectedInferredType = self::stringifyType($expectedInferredType);
		}

		$this->assertRealResultMatchesExpected($result, $expectedFirstResult, $driver, $configName, $dql, $sql, $dataset, $phpVersion, $stringify);
		$this->assertRealResultMatchesInferred($result, $driver, $configName, $dql, $sql, $dataset, $phpVersion, $inferredType, $realResultType);
		$this->assertInferredResultMatchesExpected($result, $driver, $configName, $dql, $sql, $dataset, $phpVersion, $inferredType, $expectedInferredType);
	}

	/**
	 * @param array<string, mixed> $connectionParams
	 */
	private function createConnection(
		array $connectionParams
	): Connection
	{
		$connectionConfig = new DbalConfiguration();
		$connectionConfig->setMiddlewares([
			new Middleware($this->createMock(LoggerInterface::class)), // ensures DriverType fallback detection is tested
		]);
		$connection = DriverManager::getConnection($connectionParams, $connectionConfig);

		$schemaManager = method_exists($connection, 'createSchemaManager')
			? $connection->createSchemaManager()
			: $connection->getSchemaManager();

		if (!isset($connectionParams['dbname'])) {
			if (!in_array('foo', $schemaManager->listDatabases(), true)) {
				$connection->executeQuery('CREATE DATABASE foo');
			}
			$connection->executeQuery('USE foo');
		}

		if ($connectionParams['driver'] === 'pdo_mysql') {
			$connection->executeQuery('SET GLOBAL max_connections = 1000');
		}

		return $connection;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return Query<mixed> $query
	 */
	private function getQuery(
		Connection $connection,
		string $dqlTemplate,
		array $data
	): Query
	{
		if (!DbalType::hasType(MixedCustomType::NAME)) {
			DbalType::addType(MixedCustomType::NAME, MixedCustomType::class);
		}

		$config = $this->createOrmConfig();
		$entityManager = new EntityManager($connection, $config);

		$schemaTool = new SchemaTool($entityManager);
		$classes = $entityManager->getMetadataFactory()->getAllMetadata();
		$schemaTool->dropSchema($classes);
		$schemaTool->createSchema($classes);

		$relatedEntity = new PlatformRelatedEntity();
		$relatedEntity->id = 1;
		$entityManager->persist($relatedEntity);

		foreach ($data as $rowData) {
			$entity = new PlatformEntity();
			$entity->related_entity = $relatedEntity;

			foreach ($rowData as $column => $value) {
				$entity->$column = $value; // @phpstan-ignore-line Intentionally dynamic
			}
			$entityManager->persist($entity);
		}

		$entityManager->flush();

		$dql = sprintf($dqlTemplate, PlatformEntity::class);

		return $entityManager->createQuery($dql);
	}

	/**
	 * @param Query<mixed> $query
	 */
	private function getInferredType(Query $query, bool $failOnInvalidConnection): Type
	{
		$typeBuilder = new QueryResultTypeBuilder();
		$phpVersion = new PhpVersion(PHP_VERSION_ID); // @phpstan-ignore-line ctor not in bc promise
		QueryResultTypeWalker::walk(
			$query,
			$typeBuilder,
			self::getContainer()->getByType(DescriptorRegistry::class),
			$phpVersion,
			new DriverDetector($failOnInvalidConnection)
		);

		return $typeBuilder->getResultType();
	}

	/**
	 * @param mixed $realResult
	 * @param mixed $expectedFirstResult
	 */
	private function assertRealResultMatchesExpected(
		$realResult,
		$expectedFirstResult,
		string $driver,
		string $configName,
		string $dql,
		string $sql,
		string $dataset,
		int $phpVersion,
		bool $stringified
	): void
	{
		$humanReadablePhpVersion = $this->getHumanReadablePhpVersion($phpVersion);

		$firstResult = reset($realResult);
		$realFirstResult = var_export($firstResult, true);
		$expectedFirstResultExported = var_export($expectedFirstResult, true);

		$is = $stringified
			? new IsEqual($expectedFirstResult) // loose comparison for stringified
			: new IsIdentical($expectedFirstResult);

		if ($stringified && $firstResult !== null) {
			self::assertIsString(
				$firstResult,
				sprintf(
					"Stringified result returned non-string\n\nDriver: %s\nConfig: %s\nDataset: %s\nDQL: %s\nPHP: %s\nReal first item: %s\n",
					$driver,
					$configName,
					$dataset,
					$dql,
					$humanReadablePhpVersion,
					$realFirstResult
				)
			);
		}

		self::assertThat(
			$firstResult,
			$is,
			sprintf(
				"Mismatch between expected result and fetched result\n\nDriver: %s\nConfig: %s\nDataset: %s\nDQL: %s\nSQL: %s\nPHP: %s\nReal first item: %s\nExpected first item: %s\n",
				$driver,
				$configName,
				$dataset,
				$dql,
				$sql,
				$humanReadablePhpVersion,
				$realFirstResult,
				$expectedFirstResultExported
			)
		);
	}

	/**
	 * @param mixed $realResult
	 */
	private function assertRealResultMatchesInferred(
		$realResult,
		string $driver,
		string $configName,
		string $dql,
		string $sql,
		string $dataset,
		int $phpVersion,
		Type $inferredType,
		Type $realType
	): void
	{
		$firstResult = reset($realResult);
		$realFirstResult = var_export($firstResult, true);

		self::assertTrue(
			$inferredType->accepts($realType, true)->yes(),
			sprintf(
				"Inferred type does not accept fetched result!\n\nDriver: %s\nConfig: %s\nDataset: %s\nDQL: %s\nSQL: %s\nPHP: %s\nReal first result: %s\nInferred type: %s\nReal type: %s\n",
				$driver,
				$configName,
				$dataset,
				$dql,
				$sql,
				$this->getHumanReadablePhpVersion($phpVersion),
				$realFirstResult,
				$inferredType->describe(VerbosityLevel::precise()),
				$realType->describe(VerbosityLevel::precise())
			)
		);
	}

	/**
	 * @param mixed $result
	 */
	private function assertInferredResultMatchesExpected(
		$result,
		string $driver,
		string $configName,
		string $dql,
		string $sql,
		string $dataset,
		int $phpVersion,
		Type $inferredType,
		Type $expectedFirstItemType
	): void
	{
		$firstResult = reset($result);
		$realFirstResult = var_export($firstResult, true);

		self::assertTrue($inferredType->isConstantArray()->yes());
		$inferredFirstItemType = $inferredType->getFirstIterableValueType();

		self::assertTrue(
			$inferredFirstItemType->equals($expectedFirstItemType),
			sprintf(
				"Mismatch between inferred result and expected type\n\nDriver: %s\nConfig: %s\nDataset: %s\nDQL: %s\nSQL: %s\nPHP: %s\nReal first result: %s\nFirst item inferred as: %s\nFirst item expected type: %s\n",
				$driver,
				$configName,
				$dataset,
				$dql,
				$sql,
				$this->getHumanReadablePhpVersion($phpVersion),
				$realFirstResult,
				$inferredFirstItemType->describe(VerbosityLevel::precise()),
				$expectedFirstItemType->describe(VerbosityLevel::precise())
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getConnectionParamsForDriver(string $driver): array
	{
		switch ($driver) {
			case 'pdo_mysql':
			case 'mysqli':
				return [
					'host' => getenv('MYSQL_HOST'),
					'user' => 'root',
					'password' => 'secret',
					'dbname' => 'foo',
				];
			case 'pdo_pgsql':
			case 'pgsql':
				return [
					'host' => getenv('PGSQL_HOST'),
					'user' => 'root',
					'password' => 'secret',
					'dbname' => 'foo',
				];
			case 'pdo_sqlite':
			case 'sqlite3':
				return [
					'memory' => true,
					'dbname' => 'foo',
				];
			case 'pdo_sqlsrv':
			case 'sqlsrv':
				return [
					'host' => getenv('MSSQL_HOST'),
					'user' => 'SA',
					'password' => 'Secret.123',
					// user database is created after connection
				];
			default:
				throw new LogicException('Unknown driver: ' . $driver);
		}
	}

	private function getSampleServerVersionForDriver(string $driver): string
	{
		switch ($driver) {
			case 'pdo_mysql':
			case 'mysqli':
				return '8.0.0';
			case 'pdo_pgsql':
			case 'pgsql':
				return '13.0.0';
			case 'pdo_sqlite':
			case 'sqlite3':
				return '3.0.0';
			case 'pdo_sqlsrv':
			case 'sqlsrv':
				return '15.0.0';
			default:
				throw new LogicException('Unknown driver: ' . $driver);
		}
	}

	private static function bool(): Type
	{
		return new BooleanType();
	}

	private static function boolOrNull(): Type
	{
		return TypeCombinator::addNull(new BooleanType());
	}

	private static function numericString(): Type
	{
		return new IntersectionType([
			new StringType(),
			new AccessoryNumericStringType(),
		]);
	}

	private static function string(): Type
	{
		return new StringType();
	}

	private static function numericStringOrNull(): Type
	{
		return TypeCombinator::addNull(new IntersectionType([
			new StringType(),
			new AccessoryNumericStringType(),
		]));
	}

	private static function int(): Type
	{
		return new IntegerType();
	}

	private static function intNonNegative(): Type
	{
		return IntegerRangeType::fromInterval(0, null);
	}

	private static function intNonNegativeOrNull(): Type
	{
		return TypeCombinator::addNull(IntegerRangeType::fromInterval(0, null));
	}

	private static function intOrNull(): Type
	{
		return TypeCombinator::addNull(new IntegerType());
	}

	private static function stringOrNull(): Type
	{
		return TypeCombinator::addNull(new StringType());
	}

	private static function float(): Type
	{
		return new FloatType();
	}

	private static function floatOrInt(): Type
	{
		return TypeCombinator::union(self::float(), self::int());
	}

	private static function floatOrIntOrNull(): Type
	{
		return TypeCombinator::addNull(self::floatOrInt());
	}

	private static function mixed(): Type
	{
		return new MixedType();
	}

	private static function floatOrNull(): Type
	{
		return TypeCombinator::addNull(new FloatType());
	}

	/**
	 * @return array<array<string, mixed>>
	 */
	public static function dataNone(): array
	{
		return [];
	}

	/**
	 * @return array<array<string, mixed>>
	 */
	public static function dataDefault(): array
	{
		return [
			[
				'id' => '1',
				'col_bool' => true,
				'col_bool_nullable' => null,
				'col_float' => 0.125,
				'col_float_nullable' => null,
				'col_decimal' => '0.1',
				'col_decimal_nullable' => null,
				'col_int' => 9,
				'col_int_nullable' => null,
				'col_bigint' => '2147483648',
				'col_bigint_nullable' => null,
				'col_string' => 'foobar',
				'col_string_nullable' => null,
				'col_mixed' => 1,
				'col_datetime' => new DateTime('2024-01-31 12:59:59'),
			],
		];
	}

	/**
	 * @return array<array<string, mixed>>
	 */
	public static function dataAllIntLike(): array
	{
		return [
			[
				'id' => '1',
				'col_bool' => true,
				'col_bool_nullable' => null,
				'col_float' => 1,
				'col_float_nullable' => null,
				'col_decimal' => '1',
				'col_decimal_nullable' => null,
				'col_int' => 1,
				'col_int_nullable' => null,
				'col_bigint' => '1',
				'col_bigint_nullable' => null,
				'col_string' => '1',
				'col_string_nullable' => null,
				'col_mixed' => 1,
				'col_datetime' => new DateTime('2024-01-31 12:59:59'),
			],
		];
	}


	/**
	 * @return array<array<string, mixed>>
	 */
	public static function dataSqrt(): array
	{
		return [
			[
				'id' => '1',
				'col_bool' => true,
				'col_bool_nullable' => null,
				'col_float' => 1.0,
				'col_float_nullable' => null,
				'col_decimal' => '1.0',
				'col_decimal_nullable' => null,
				'col_int' => 9,
				'col_int_nullable' => null,
				'col_bigint' => '90000000000',
				'col_bigint_nullable' => null,
				'col_string' => 'foobar',
				'col_string_nullable' => null,
				'col_mixed' => 1,
				'col_datetime' => new DateTime('2024-01-31 12:59:59'),
			],
		];
	}

	private static function stringifyType(Type $type): Type
	{
		return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
			if ($type instanceof UnionType || $type instanceof IntersectionType) {
				return $traverse($type);
			}

			if ($type instanceof IntegerType) {
				return $type->toString();
			}

			if ($type instanceof FloatType) {
				return self::numericString();
			}

			if ($type instanceof BooleanType) {
				return $type->toInteger()->toString();
			}

			return $traverse($type);
		});
	}

	private function resolveDefaultStringification(?string $driver, int $php, string $configName): bool
	{
		if ($configName === self::CONFIG_DEFAULT) {
			if ($php < 80100) {
				return $driver === DriverDetector::PDO_MYSQL || $driver === DriverDetector::PDO_SQLITE;
			}

			return false;
		}

		if ($configName === self::CONFIG_STRINGIFY || $configName === self::CONFIG_STRINGIFY_NO_EMULATE) {
			return $driver === DriverDetector::PDO_PGSQL
				|| $driver === DriverDetector::PDO_MYSQL
				|| $driver === DriverDetector::PDO_SQLITE;
		}

		if ($configName === self::CONFIG_NO_EMULATE) {
			return false;
		}

		throw new LogicException('Unknown config name: ' . $configName);
	}

	private function resolveDefaultBooleanStringification(?string $driver, int $php, string $configName): bool
	{
		if ($php < 80100 && $driver === DriverDetector::PDO_PGSQL) {
			return false; // pdo_pgsql does not stringify booleans even with ATTR_STRINGIFY_FETCHES prior to PHP 8.1
		}

		return $this->resolveDefaultStringification($driver, $php, $configName);
	}

	private function getHumanReadablePhpVersion(int $phpVersion): string
	{
		return floor($phpVersion / 10000) . '.' . floor(($phpVersion % 10000) / 100);
	}

	private static function hasDbal4(): bool
	{
		if (!class_exists(InstalledVersions::class)) {
			return false;
		}

		return InstalledVersions::satisfies(new VersionParser(), 'doctrine/dbal', '4.*');
	}

	private function shouldStringify(string $stringification, ?string $driverType, int $phpVersion, string $configName): bool
	{
		if ($stringification === self::STRINGIFY_NONE) {
			return false;
		}

		if ($stringification === self::STRINGIFY_DEFAULT) {
			return $this->resolveDefaultStringification($driverType, $phpVersion, $configName);
		}

		if ($stringification === self::STRINGIFY_PG_BOOL) {
			return $this->resolveDefaultBooleanStringification($driverType, $phpVersion, $configName);
		}

		throw new LogicException('Unknown stringification: ' . $stringification);
	}

	/**
	 * @param Query<mixed> $query
	 * @param self::INVALID_* $invalidSetup
	 * @return Query<mixed>
	 */
	private function cloneQueryAndInjectInvalidConnection(Query $query, string $driver, string $invalidSetup): Query
	{
		if ($query->getDQL() === null) {
			throw new LogicException('Query does not have DQL');
		}

		$connectionConfig = new DbalConfiguration();

		if ($invalidSetup === self::INVALID_CONNECTION_UNKNOWN_DRIVER) {
			$connectionConfig->setMiddlewares([
				new Middleware($this->createMock(LoggerInterface::class)), // ensures DriverType fallback detection is used
			]);
		}

		$serverVersion = $this->getSampleServerVersionForDriver($driver);
		$connection = DriverManager::getConnection([ // @phpstan-ignore-line ignore dynamic driver
			'driver' => $driver,
			'user' => 'invalid',
			'serverVersion' => $serverVersion, // otherwise the connection fails while trying to determine the platform
		], $connectionConfig);
		$entityManager = new EntityManager($connection, $this->createOrmConfig());
		$newQuery = new Query($entityManager);
		$newQuery->setDQL($query->getDQL());
		return $newQuery;
	}

	private function createOrmConfig(): Configuration
	{
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

		$config->addCustomStringFunction('INT_PI', TypedExpressionIntegerPiFunction::class);
		$config->addCustomStringFunction('BOOL_PI', TypedExpressionBooleanPiFunction::class);
		$config->addCustomStringFunction('STRING_PI', TypedExpressionStringPiFunction::class);

		return $config;
	}

	private function determineTypeForKnownDriverUnknownSetup(Type $originalExpectedType, string $stringify): Type
	{
		if ($stringify === self::STRINGIFY_NONE) {
			return $originalExpectedType;
		}

		return TypeCombinator::union($originalExpectedType, self::stringifyType($originalExpectedType));
	}

	private function determineTypeForUnknownDriverUnknownSetup(Type $originalExpectedType, string $stringify): Type
	{
		if ($stringify === self::STRINGIFY_NONE) {
			return $originalExpectedType; // those are direct column fetches, those always work (this is mild abuse of this flag)
		}

		return new MixedType();
	}

}
