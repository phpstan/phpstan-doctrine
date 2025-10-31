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
use PHPStan\Type\Accessory\AccessoryLowercaseStringType;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\Accessory\AccessoryUppercaseStringType;
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
	private const STRINGIFY_PG_FLOAT = 'pg_float';

	private const CONFIG_DEFAULT = 'default';
	private const CONFIG_STRINGIFY = 'pdo_stringify';
	private const CONFIG_NO_EMULATE = 'pdo_no_emulate';
	private const CONFIG_STRINGIFY_NO_EMULATE = 'pdo_stringify_no_emulate';

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
	public function testPdoMysqlDefault(
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
		$this->performDriverTest(
			'pdo_mysql',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$mysqlExpectedType,
			$mysqlExpectedResult,
			$stringify,
		);
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
	public function testPdoMysqlStringify(
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
		$this->performDriverTest(
			'pdo_mysql',
			self::CONFIG_STRINGIFY,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$mysqlExpectedType,
			$mysqlExpectedResult,
			$stringify,
		);
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
	public function testPdoMysqlNoEmulate(
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
		$this->performDriverTest(
			'pdo_mysql',
			self::CONFIG_NO_EMULATE,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$mysqlExpectedType,
			$mysqlExpectedResult,
			$stringify,
		);
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
	public function testPdoMysqlStringifyNoEmulate(
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
		$this->performDriverTest(
			'pdo_mysql',
			self::CONFIG_STRINGIFY_NO_EMULATE,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$mysqlExpectedType,
			$mysqlExpectedResult,
			$stringify,
		);
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
	public function testPdoMysqliDefault(
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
		$this->performDriverTest(
			'mysqli',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$mysqlExpectedType,
			$mysqlExpectedResult,
			$stringify,
		);
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
	public function testPdoSqliteDefault(
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
		$this->performDriverTest(
			'pdo_sqlite',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$sqliteExpectedType,
			$sqliteExpectedResult,
			$stringify,
		);
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
	public function testPdoSqliteStringify(
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
		$this->performDriverTest(
			'pdo_sqlite',
			self::CONFIG_STRINGIFY,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$sqliteExpectedType,
			$sqliteExpectedResult,
			$stringify,
		);
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
	public function testPdoSqlite3(
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
		$this->performDriverTest(
			'sqlite3',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$sqliteExpectedType,
			$sqliteExpectedResult,
			$stringify,
		);
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
	public function testPdoPgsqlDefault(
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
		$this->performDriverTest(
			'pdo_pgsql',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$pdoPgsqlExpectedType,
			$pdoPgsqlExpectedResult,
			$stringify,
		);
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
	public function testPdoPgsqlStringify(
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
		$this->performDriverTest(
			'pdo_pgsql',
			self::CONFIG_STRINGIFY,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$pdoPgsqlExpectedType,
			$pdoPgsqlExpectedResult,
			$stringify,
		);
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
	public function testPgsql(
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
		$this->performDriverTest(
			'pgsql',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$pgsqlExpectedType,
			$pgsqlExpectedResult,
			$stringify,
		);
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
	public function testUnknownDriver(
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
		$this->performDriverTest(
			'pdo_mysql',
			self::CONFIG_DEFAULT,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$this->determineTypeForUnknownDriverUnknownSetup($mysqlExpectedType, $stringify),
			$mysqlExpectedResult,
			$stringify,
			true,
		);
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
	public function testUnknownDriverStringify(
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
		$this->performDriverTest(
			'pdo_mysql',
			self::CONFIG_STRINGIFY,
			$data,
			$dqlTemplate,
			(string) $this->dataName(),
			PHP_VERSION_ID,
			$this->determineTypeForUnknownDriverUnknownSetup($mysqlExpectedType, $stringify),
			$mysqlExpectedResult,
			$stringify,
			true,
		);
	}

	/**
	 * @return iterable<string, mixed>
	 */
	public static function provideCases(): iterable
	{
		yield ' -1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT -1 FROM %s t',
			'mysqlExpectedType' => new ConstantIntegerType(-1),
			'sqliteExpectedType' => new ConstantIntegerType(-1),
			'pdoPgsqlExpectedType' => new ConstantIntegerType(-1),
			'pgsqlExpectedType' => new ConstantIntegerType(-1),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => -1,
			'sqliteExpectedResult' => -1,
			'pdoPgsqlExpectedResult' => -1,
			'pgsqlExpectedResult' => -1,
			'mssqlExpectedResult' => -1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 1 FROM %s t',
			'mysqlExpectedType' => new ConstantIntegerType(1),
			'sqliteExpectedType' => new ConstantIntegerType(1),
			'pdoPgsqlExpectedType' => new ConstantIntegerType(1),
			'pgsqlExpectedType' => new ConstantIntegerType(1),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1.0' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 1.0 FROM %s t',
			'mysqlExpectedType' => new ConstantStringType('1.0'),
			'sqliteExpectedType' => new ConstantFloatType(1.0),
			'pdoPgsqlExpectedType' => new ConstantStringType('1.0'),
			'pgsqlExpectedType' => new ConstantStringType('1.0'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1.00' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 1.00 FROM %s t',
			'mysqlExpectedType' => new ConstantStringType('1.00'),
			'sqliteExpectedType' => new ConstantFloatType(1.0),
			'pdoPgsqlExpectedType' => new ConstantStringType('1.00'),
			'pgsqlExpectedType' => new ConstantStringType('1.00'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00',
			'pgsqlExpectedResult' => '1.00',
			'mssqlExpectedResult' => '1.00',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 0.1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 0.1 FROM %s t',
			'mysqlExpectedType' => new ConstantStringType('0.1'),
			'sqliteExpectedType' => new ConstantFloatType(0.1),
			'pdoPgsqlExpectedType' => new ConstantStringType('0.1'),
			'pgsqlExpectedType' => new ConstantStringType('0.1'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.1',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.1',
			'pgsqlExpectedResult' => '0.1',
			'mssqlExpectedResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 0.10' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 0.10 FROM %s t',
			'mysqlExpectedType' => new ConstantStringType('0.10'),
			'sqliteExpectedType' => new ConstantFloatType(0.1),
			'pdoPgsqlExpectedType' => new ConstantStringType('0.10'),
			'pgsqlExpectedType' => new ConstantStringType('0.10'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.10',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.10',
			'pgsqlExpectedResult' => '0.10',
			'mssqlExpectedResult' => '.10',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '0.125e0' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 0.125e0 FROM %s t',
			'mysqlExpectedType' => new ConstantFloatType(0.125),
			'sqliteExpectedType' => new ConstantFloatType(0.125),
			'pdoPgsqlExpectedType' => new ConstantStringType('0.125'),
			'pgsqlExpectedType' => new ConstantStringType('0.125'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => '0.125',
			'pgsqlExpectedResult' => '0.125',
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield ' 1e0' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 1e0 FROM %s t',
			'mysqlExpectedType' => new ConstantFloatType(1.0),
			'sqliteExpectedType' => new ConstantFloatType(1.0),
			'pdoPgsqlExpectedType' => new ConstantStringType('1'),
			'pgsqlExpectedType' => new ConstantStringType('1'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield " '1'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT '1' FROM %s t",
			'mysqlExpectedType' => new ConstantStringType('1'),
			'sqliteExpectedType' => new ConstantStringType('1'),
			'pdoPgsqlExpectedType' => new ConstantStringType('1'),
			'pgsqlExpectedType' => new ConstantStringType('1'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => '1',
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => '1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield " '1e0'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT '1e0' FROM %s t",
			'mysqlExpectedType' => new ConstantStringType('1e0'),
			'sqliteExpectedType' => new ConstantStringType('1e0'),
			'pdoPgsqlExpectedType' => new ConstantStringType('1e0'),
			'pgsqlExpectedType' => new ConstantStringType('1e0'),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1e0',
			'sqliteExpectedResult' => '1e0',
			'pdoPgsqlExpectedResult' => '1e0',
			'pgsqlExpectedResult' => '1e0',
			'mssqlExpectedResult' => '1e0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 + 1) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2,
			'sqliteExpectedResult' => 2,
			'pdoPgsqlExpectedResult' => 2,
			'pgsqlExpectedResult' => 2,
			'mssqlExpectedResult' => 2,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + 'foo'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 + 'foo') FROM %s t",
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + '1.0'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 + '1.0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 2.0,
			'sqliteExpectedResult' => 2.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + '1'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 + '1') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2.0,
			'sqliteExpectedResult' => 2,
			'pdoPgsqlExpectedResult' => 2,
			'pgsqlExpectedResult' => 2,
			'mssqlExpectedResult' => 2,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 + '1e0'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 + '1e0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 2.0,
			'sqliteExpectedResult' => 2.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + 1 * 1 - 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 + 1 * 1 - 1) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 + 1 * 1 / 1 - 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 + 1 * 1 / 1 - 1) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_int' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_int FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 18,
			'sqliteExpectedResult' => 18,
			'pdoPgsqlExpectedResult' => 18,
			'pgsqlExpectedResult' => 18,
			'mssqlExpectedResult' => 18,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint + t.col_bigint' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bigint + t.col_bigint FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 4294967296,
			'sqliteExpectedResult' => 4294967296,
			'pdoPgsqlExpectedResult' => 4294967296,
			'pgsqlExpectedResult' => 4294967296,
			'mssqlExpectedResult' => '4294967296',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 9.125,
			'sqliteExpectedResult' => 9.125,
			'pdoPgsqlExpectedResult' => 9.125,
			'pgsqlExpectedResult' => 9.125,
			'mssqlExpectedResult' => 9.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_int + t.col_mixed' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_mixed FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 10,
			'sqliteExpectedResult' => 10,
			'pdoPgsqlExpectedResult' => 10,
			'pgsqlExpectedResult' => 10,
			'mssqlExpectedResult' => 10,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint + t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bigint + t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2147483648.125,
			'sqliteExpectedResult' => 2147483648.125,
			'pdoPgsqlExpectedResult' => 2147483648.125,
			'pgsqlExpectedResult' => 2147483648.125,
			'mssqlExpectedResult' => 2147483648.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_bigint + t.col_float (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_bigint + t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2.0,
			'sqliteExpectedResult' => 2.0,
			'pdoPgsqlExpectedResult' => 2.0,
			'pgsqlExpectedResult' => 2.0,
			'mssqlExpectedResult' => 2.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_float + t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float + t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.25,
			'sqliteExpectedResult' => 0.25,
			'pdoPgsqlExpectedResult' => 0.25,
			'pgsqlExpectedResult' => 0.25,
			'mssqlExpectedResult' => 0.25,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_int + t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9.1',
			'sqliteExpectedResult' => 9.1,
			'pdoPgsqlExpectedResult' => '9.1',
			'pgsqlExpectedResult' => '9.1',
			'mssqlExpectedResult' => '9.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '2.0',
			'sqliteExpectedResult' => 2,
			'pdoPgsqlExpectedResult' => '2.0',
			'pgsqlExpectedResult' => '2.0',
			'mssqlExpectedResult' => '2.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float + t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.225,
			'sqliteExpectedResult' => 0.225,
			'pdoPgsqlExpectedResult' => 0.225,
			'pgsqlExpectedResult' => 0.225,
			'mssqlExpectedResult' => 0.225,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_float + t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_float + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2.0,
			'sqliteExpectedResult' => 2.0,
			'pdoPgsqlExpectedResult' => 2.0,
			'pgsqlExpectedResult' => 2.0,
			'mssqlExpectedResult' => 2.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_decimal + t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_decimal + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '2.0',
			'sqliteExpectedResult' => 2,
			'pdoPgsqlExpectedResult' => '2.0',
			'pgsqlExpectedResult' => '2.0',
			'mssqlExpectedResult' => '2.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_float + t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_float + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 9.225,
			'sqliteExpectedResult' => 9.225,
			'pdoPgsqlExpectedResult' => 9.225,
			'pgsqlExpectedResult' => 9.225,
			'mssqlExpectedResult' => 9.225,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_decimal + t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal + t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.2',
			'sqliteExpectedResult' => 0.2,
			'pdoPgsqlExpectedResult' => '0.2',
			'pgsqlExpectedResult' => '0.2',
			'mssqlExpectedResult' => '.2',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_string' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 9.0,
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_string (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2.0,
			'sqliteExpectedResult' => 2,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 2,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_bool' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_bool FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null,
			'mssqlExpectedType' => self::mixed(), // Undefined function
			'mysqlExpectedResult' => 10,
			'sqliteExpectedResult' => 10,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 10,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float + t.col_string' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float + t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal + t.col_bool' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal + t.col_bool FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.1',
			'sqliteExpectedResult' => 1.1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => '1.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal + t.col_string' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal + t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 0.1,
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int + t.col_int_nullable' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int + t.col_int_nullable FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_int' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_int FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_bigint / t.col_bigint' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bigint / t.col_bigint FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => '1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 72.0,
			'sqliteExpectedResult' => 72.0,
			'pdoPgsqlExpectedResult' => 72.0,
			'pgsqlExpectedResult' => 72.0,
			'mssqlExpectedResult' => 72.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_int / t.col_float / t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_float / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 720.0,
			'sqliteExpectedResult' => 720.0,
			'pdoPgsqlExpectedResult' => 720.0,
			'pgsqlExpectedResult' => 720.0,
			'mssqlExpectedResult' => 720.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_bigint / t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bigint / t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 17179869184.0,
			'sqliteExpectedResult' => 17179869184.0,
			'pdoPgsqlExpectedResult' => 17179869184.0,
			'pgsqlExpectedResult' => 17179869184.0,
			'mssqlExpectedResult' => 17179869184.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_float / t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float / t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_int / t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '90.0000',
			'sqliteExpectedResult' => 90.0,
			'pdoPgsqlExpectedResult' => '90.0000000000000000',
			'pgsqlExpectedResult' => '90.0000000000000000',
			'mssqlExpectedResult' => '90.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float / t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.25,
			'sqliteExpectedResult' => 1.25,
			'pdoPgsqlExpectedResult' => 1.25,
			'pgsqlExpectedResult' => 1.25,
			'mssqlExpectedResult' => 1.25,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_decimal / t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_decimal (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_decimal / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_mixed' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal / t.col_mixed FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.10000',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.10000000000000000000',
			'pgsqlExpectedResult' => '0.10000000000000000000',
			'mssqlExpectedResult' => '.100000000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_string' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_string (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_string / t.col_int' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_string / t.col_int FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_bool' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_bool FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9.0000',
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_float / t.col_string' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float / t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_string / t.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_string / t.col_float FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_bool' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal / t.col_bool FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.10000',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => '.100000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_bool (int data)' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT t.col_decimal / t.col_bool FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_decimal / t.col_string' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal / t.col_string FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_string / t.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_string / t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 't.col_int / t.col_int_nullable' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int / t.col_int_nullable FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 - 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 - 1) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 * 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 * 1) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 * '1'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 * '1') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 * '1.0'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 * '1.0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 / 1' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 / 1) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 / 1.0' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 / 1.0) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '1 / 1e0' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (1 / 1e0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "'foo' / 1" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT ('foo' / 1) FROM %s t",
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 / 'foo'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 / 'foo') FROM %s t",
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 / '1'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 / '1') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "'1' / 1" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT ('1' / 1) FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "1 / '1.0'" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT (1 / '1.0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '2147483648 ' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 2147483648 FROM %s t',
			'mysqlExpectedType' => new ConstantIntegerType(2147483648),
			'sqliteExpectedType' => new ConstantIntegerType(2147483648),
			'pdoPgsqlExpectedType' => new ConstantIntegerType(2147483648),
			'pgsqlExpectedType' => new ConstantIntegerType(2147483648),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2147483648,
			'sqliteExpectedResult' => 2147483648,
			'pdoPgsqlExpectedResult' => 2147483648,
			'pgsqlExpectedResult' => 2147483648,
			'mssqlExpectedResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "''" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT \'\' FROM %s t',
			'mysqlExpectedType' => new ConstantStringType(''),
			'sqliteExpectedType' => new ConstantStringType(''),
			'pdoPgsqlExpectedType' => new ConstantStringType(''),
			'pgsqlExpectedType' => new ConstantStringType(''),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '',
			'sqliteExpectedResult' => '',
			'pdoPgsqlExpectedResult' => '',
			'pgsqlExpectedResult' => '',
			'mssqlExpectedResult' => '',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '(TRUE)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (TRUE) FROM %s t',
			'mysqlExpectedType' => new ConstantIntegerType(1),
			'sqliteExpectedType' => new ConstantIntegerType(1),
			'pdoPgsqlExpectedType' => new ConstantBooleanType(true),
			'pgsqlExpectedType' => new ConstantBooleanType(true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => true,
			'pgsqlExpectedResult' => true,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_PG_BOOL,
		];

		yield '(FALSE)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT (FALSE) FROM %s t',
			'mysqlExpectedType' => new ConstantIntegerType(0),
			'sqliteExpectedType' => new ConstantIntegerType(0),
			'pdoPgsqlExpectedType' => new ConstantBooleanType(false),
			'pgsqlExpectedType' => new ConstantBooleanType(false),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => false,
			'pgsqlExpectedResult' => false,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_PG_BOOL,
		];

		yield 't.col_bool' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bool FROM %s t',
			'mysqlExpectedType' => self::bool(),
			'sqliteExpectedType' => self::bool(),
			'pdoPgsqlExpectedType' => self::bool(),
			'pgsqlExpectedType' => self::bool(),
			'mssqlExpectedType' => self::bool(),
			'mysqlExpectedResult' => true,
			'sqliteExpectedResult' => true,
			'pdoPgsqlExpectedResult' => true,
			'pgsqlExpectedResult' => true,
			'mssqlExpectedResult' => true,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_bool_nullable' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bool_nullable FROM %s t',
			'mysqlExpectedType' => self::boolOrNull(),
			'sqliteExpectedType' => self::boolOrNull(),
			'pdoPgsqlExpectedType' => self::boolOrNull(),
			'pgsqlExpectedType' => self::boolOrNull(),
			'mssqlExpectedType' => self::boolOrNull(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COALESCE(t.col_bool, t.col_bool)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_bool, t.col_bool) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::bool(),
			'pgsqlExpectedType' => self::bool(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => true,
			'pgsqlExpectedResult' => true,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_PG_BOOL,
		];

		yield 'COALESCE(t.col_decimal, t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_decimal, t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_float, t.col_float)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_float, t.col_float) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'COALESCE(t.col_float, t.col_float) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_float, t.col_float) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 't.col_decimal' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_decimal FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::numericString(false, true),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::numericString(false, true),
			'mysqlExpectedResult' => '0.1',
			'sqliteExpectedResult' => '0.1',
			'pdoPgsqlExpectedResult' => '0.1',
			'pgsqlExpectedResult' => '0.1',
			'mssqlExpectedResult' => '.1',
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_int' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_int FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::int(),
			'mysqlExpectedResult' => 9,
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_bigint' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_bigint FROM %s t',
			'mysqlExpectedType' => self::hasDbal4() ? self::int() : self::numericString(true, true),
			'sqliteExpectedType' => self::hasDbal4() ? self::int() : self::numericString(true, true),
			'pdoPgsqlExpectedType' => self::hasDbal4() ? self::int() : self::numericString(true, true),
			'pgsqlExpectedType' => self::hasDbal4() ? self::int() : self::numericString(true, true),
			'mssqlExpectedType' => self::hasDbal4() ? self::int() : self::numericString(true, true),
			'mysqlExpectedResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'sqliteExpectedResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'pdoPgsqlExpectedResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'pgsqlExpectedResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'mssqlExpectedResult' => self::hasDbal4() ? 2147483648 : '2147483648',
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_float' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_float FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::float(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'AVG(t.col_float)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'AVG(t.col_float) + no data' => [
			'data' => self::dataNone(),
			'dqlTemplate' => 'SELECT AVG(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'AVG(t.col_float) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_float) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'AVG(t.col_float_nullable) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_float_nullable) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'AVG(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(false, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(false, true),
			'pgsqlExpectedType' => self::numericStringOrNull(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.10000',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.10000000000000000000',
			'pgsqlExpectedResult' => '0.10000000000000000000',
			'mssqlExpectedResult' => '.100000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT AVG(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(false, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(false, true),
			'pgsqlExpectedType' => self::numericStringOrNull(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::floatOrNull(), // always float|null, see https://www.sqlite.org/lang_aggfunc.html
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9.0000',
			'sqliteExpectedResult' => 9.0,
			'pdoPgsqlExpectedResult' => '9.0000000000000000',
			'pgsqlExpectedResult' => '9.0000000000000000',
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_bool)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_bool) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // perand data type bit is invalid for avg operator.
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_string) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type nvarchar is invalid for avg operator
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(1) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT AVG('1') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('1.0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT AVG('1.0') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('1e0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT AVG('1e0') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "AVG('foo')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT AVG('foo') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(1) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(1.0) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(1e0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(1.0) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.00000',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.00000000000000000000',
			'pgsqlExpectedResult' => '1.00000000000000000000',
			'mssqlExpectedResult' => '1.000000',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'AVG(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT AVG(t.col_bigint) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '2147483648.0000',
			'sqliteExpectedResult' => 2147483648.0,
			'pdoPgsqlExpectedResult' => '2147483648.00000000',
			'pgsqlExpectedResult' => '2147483648.00000000',
			'mssqlExpectedResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_float)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SUM(t.col_float) + no data' => [
			'data' => self::dataNone(),
			'dqlTemplate' => 'SELECT SUM(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SUM(t.col_float) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_float) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield '1 + -(CASE WHEN MIN(t.col_float) = 0 THEN SUM(t.col_float) ELSE 0 END)' => [ // agg function (causing null) deeply inside AST
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT 1 + -(CASE WHEN MIN(t.col_float) = 0 THEN SUM(t.col_float) ELSE 0 END) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrIntOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SUM(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(false, true),
			'sqliteExpectedType' => self::floatOrIntOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(false, true),
			'pgsqlExpectedType' => self::numericStringOrNull(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.1',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.1',
			'pgsqlExpectedResult' => '0.1',
			'mssqlExpectedResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT SUM(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(false, true),
			'sqliteExpectedType' => self::floatOrIntOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(false, true),
			'pgsqlExpectedType' => self::numericStringOrNull(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9',
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '-SUM(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT -SUM(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '-9',
			'sqliteExpectedResult' => -9,
			'pdoPgsqlExpectedResult' => -9,
			'pgsqlExpectedResult' => -9,
			'mssqlExpectedResult' => -9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '-SUM(t.col_int) + no data' => [
			'data' => self::dataNone(),
			'dqlTemplate' => 'SELECT -SUM(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_bool)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_bool) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_string) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('foo')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT SUM('foo') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT SUM('1') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('1.0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT SUM('1.0') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "SUM('1.1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT SUM('1.1') FROM %s t",
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1.1,
			'sqliteExpectedResult' => 1.1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(1) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(1) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericString(true, true), self::int()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(true, true), self::int()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(1.0) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(1e0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(1e0) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SUM(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT SUM(t.col_bigint) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericStringOrNull(true, true), self::intOrNull()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '2147483648',
			'sqliteExpectedResult' => 2147483648,
			'pdoPgsqlExpectedResult' => '2147483648',
			'pgsqlExpectedResult' => '2147483648',
			'mssqlExpectedResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_float)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'MAX(t.col_float) + no data' => [
			'data' => self::dataNone(),
			'dqlTemplate' => 'SELECT MAX(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'MAX(t.col_float) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_float) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'MAX(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(false, true),
			'sqliteExpectedType' => self::floatOrIntOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(false, true),
			'pgsqlExpectedType' => self::numericStringOrNull(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.1',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.1',
			'pgsqlExpectedResult' => '0.1',
			'mssqlExpectedResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT MAX(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(false, true),
			'sqliteExpectedType' => self::floatOrIntOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(false, true),
			'pgsqlExpectedType' => self::numericStringOrNull(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 9,
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_bool)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_bool) FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_string) FROM %s t',
			'mysqlExpectedType' => self::stringOrNull(),
			'sqliteExpectedType' => self::stringOrNull(),
			'pdoPgsqlExpectedType' => self::stringOrNull(),
			'pgsqlExpectedType' => self::stringOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 'foobar',
			'sqliteExpectedResult' => 'foobar',
			'pdoPgsqlExpectedResult' => 'foobar',
			'pgsqlExpectedResult' => 'foobar',
			'mssqlExpectedResult' => 'foobar',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MAX('foobar')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT MAX('foobar') FROM %s t",
			'mysqlExpectedType' => TypeCombinator::addNull(self::string()),
			'sqliteExpectedType' => TypeCombinator::addNull(self::string()),
			'pdoPgsqlExpectedType' => TypeCombinator::addNull(self::string()),
			'pgsqlExpectedType' => TypeCombinator::addNull(self::string()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 'foobar',
			'sqliteExpectedResult' => 'foobar',
			'pdoPgsqlExpectedResult' => 'foobar',
			'pgsqlExpectedResult' => 'foobar',
			'mssqlExpectedResult' => 'foobar',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MAX('1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT MAX('1') FROM %s t",
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::numericStringOrNull(true, true),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => '1',
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => '1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MAX('1.0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT MAX('1.0') FROM %s t",
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::numericStringOrNull(true, true),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => '1.0',
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(1) FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1) + GROUP BY' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(1) FROM %s t GROUP BY t.col_int',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(1.0) FROM %s t',
			'mysqlExpectedType' => self::numericStringOrNull(true, true),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(1e0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(1e0) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericStringOrNull(true, true),
			'pgsqlExpectedType' => self::numericStringOrNull(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MAX(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MAX(t.col_bigint) FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2147483648,
			'sqliteExpectedResult' => 2147483648,
			'pdoPgsqlExpectedResult' => 2147483648,
			'pgsqlExpectedResult' => 2147483648,
			'mssqlExpectedResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_float)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.125,
			'pdoPgsqlExpectedResult' => 0.125,
			'pgsqlExpectedResult' => 0.125,
			'mssqlExpectedResult' => 0.125,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'ABS(t.col_decimal)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.1',
			'sqliteExpectedResult' => 0.1,
			'pdoPgsqlExpectedResult' => '0.1',
			'pgsqlExpectedResult' => '0.1',
			'mssqlExpectedResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_decimal) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT ABS(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::numericString(false, true),
			'sqliteExpectedType' => self::floatOrInt(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 9,
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield '-ABS(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT -ABS(t.col_int) FROM %s t',
			'mysqlExpectedType' => IntegerRangeType::fromInterval(null, 0),
			'sqliteExpectedType' => IntegerRangeType::fromInterval(null, 0),
			'pdoPgsqlExpectedType' => IntegerRangeType::fromInterval(null, 0),
			'pgsqlExpectedType' => IntegerRangeType::fromInterval(null, 0),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => -9,
			'sqliteExpectedResult' => -9,
			'pdoPgsqlExpectedResult' => -9,
			'pgsqlExpectedResult' => -9,
			'mssqlExpectedResult' => -9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_int_nullable)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_int_nullable) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => self::intNonNegativeOrNull(),
			'pgsqlExpectedType' => self::intNonNegativeOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_string) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // Operand data type is invalid
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_string) + int data' => [
			'data' => self::dataAllIntLike(),
			'dqlTemplate' => 'SELECT ABS(t.col_string) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_bool)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_bool) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(-1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(-1) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(1) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(1.0) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(1e0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(1e0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "ABS('1.0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT ABS('1.0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield "ABS('1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT ABS('1') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'ABS(t.col_bigint)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_bigint) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2147483648,
			'sqliteExpectedResult' => 2147483648,
			'pdoPgsqlExpectedResult' => 2147483648,
			'pgsqlExpectedResult' => 2147483648,
			'mssqlExpectedResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'ABS(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT ABS(t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_int, 0) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => null,
			'pgsqlExpectedType' => null,
			'mssqlExpectedType' => null, // Divide by zero error encountered.
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, 1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_int, 1) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_mixed, 1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_mixed, 1) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MOD(t.col_int, '1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT MOD(t.col_int, '1') FROM %s t",
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "MOD(t.col_int, '1.0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT MOD(t.col_int, '1') FROM %s t",
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_float)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_int, t.col_float) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // The data types are incompatible in the modulo operator.
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_decimal)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_int, t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.0',
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => '0.0',
			'pgsqlExpectedResult' => '0.0',
			'mssqlExpectedResult' => '.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_float, t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_float, t.col_int) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // The data types are incompatible in the modulo operator.
			'mysqlExpectedResult' => 0.125,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_decimal, t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_decimal, t.col_int) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.1',
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => '0.1',
			'pgsqlExpectedResult' => '0.1',
			'mssqlExpectedResult' => '.1',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_string, t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_string, t.col_string) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => null, // Undefined function
			'pgsqlExpectedType' => null, // Undefined function
			'mssqlExpectedType' => null, // The data types are incompatible in the modulo operator.
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_int, t.col_int) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_int, t.col_int_nullable)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_int, t.col_int_nullable) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => self::intNonNegativeOrNull(),
			'pgsqlExpectedType' => self::intNonNegativeOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(10, 7)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(10, 7) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 3,
			'sqliteExpectedResult' => 3,
			'pdoPgsqlExpectedResult' => 3,
			'pgsqlExpectedResult' => 3,
			'mssqlExpectedResult' => 3,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(10, -7)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(10, -7) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 3,
			'sqliteExpectedResult' => 3,
			'pdoPgsqlExpectedResult' => 3,
			'pgsqlExpectedResult' => 3,
			'mssqlExpectedResult' => 3,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'MOD(t.col_bigint, t.col_bigint)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT MOD(t.col_bigint, t.col_bigint) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => '0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_bigint, t.col_bigint)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BIT_AND(t.col_bigint, t.col_bigint) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 2147483648,
			'sqliteExpectedResult' => 2147483648,
			'pdoPgsqlExpectedResult' => 2147483648,
			'pgsqlExpectedResult' => 2147483648,
			'mssqlExpectedResult' => '2147483648',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_int, t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BIT_AND(t.col_int, t.col_int) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 9,
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_mixed, t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BIT_AND(t.col_mixed, t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => self::intNonNegativeOrNull(),
			'pgsqlExpectedType' => self::intNonNegativeOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_int, t.col_int_nullable)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BIT_AND(t.col_int, t.col_int_nullable) FROM %s t',
			'mysqlExpectedType' => self::intNonNegativeOrNull(),
			'sqliteExpectedType' => self::intNonNegativeOrNull(),
			'pdoPgsqlExpectedType' => self::intNonNegativeOrNull(),
			'pgsqlExpectedType' => self::intNonNegativeOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(1, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BIT_AND(1, 0) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BIT_AND(t.col_string, t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BIT_AND(t.col_string, t.col_string) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => null,
			'pgsqlExpectedType' => null,
			'mssqlExpectedType' => null,
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'DATE_DIFF(CURRENT_DATE(), CURRENT_DATE())' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT DATE_DIFF('2024-01-01 12:00', '2024-01-01 11:00') FROM %s t",
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'DATE_DIFF(CURRENT_DATE(), t.col_string_nullable)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT DATE_DIFF('2024-01-01 12:00', t.col_string_nullable) FROM %s t",
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'DATE_DIFF(CURRENT_DATE(), t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT DATE_DIFF('2024-01-01 12:00', t.col_mixed) FROM %s t",
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null,
			'pgsqlExpectedType' => null,
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => 2460310.0,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 45289,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_float)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(t.col_float) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SQRT(t.col_decimal)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(t.col_decimal) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::numericString(false, true),
			'pgsqlExpectedType' => self::numericString(false, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.000000000000000',
			'pgsqlExpectedResult' => '1.000000000000000',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_int)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 3.0,
			'sqliteExpectedResult' => 3.0,
			'pdoPgsqlExpectedResult' => 3.0,
			'pgsqlExpectedResult' => 3.0,
			'mssqlExpectedResult' => 3.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SQRT(t.col_mixed)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SQRT(t.col_int_nullable)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(t.col_int_nullable) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => PHP_VERSION_ID >= 80100 && !self::hasDbal4() ? null : self::floatOrNull(), // fails in UDF since PHP 8.1: sqrt(): Passing null to parameter #1 ($num) of type float is deprecated
			'pdoPgsqlExpectedType' => self::floatOrNull(),
			'pgsqlExpectedType' => self::floatOrNull(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => self::hasDbal4() ? null : 0.0, // 0.0 caused by UDF wired through PHP's sqrt() which returns 0.0 for null
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'SQRT(-1)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(-1) FROM %s t',
			'mysqlExpectedType' => self::floatOrNull(),
			'sqliteExpectedType' => self::floatOrNull(),
			'pdoPgsqlExpectedType' => null, // failure: cannot take square root of a negative number
			'pgsqlExpectedType' => null, // failure: cannot take square root of a negative number
			'mssqlExpectedType' => null, // An invalid floating point operation occurred.
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(1)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(1) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield "SQRT('1')" => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => "SELECT SQRT('1') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield "SQRT('1.0')" => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => "SELECT SQRT('1.0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield "SQRT('1e0')" => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => "SELECT SQRT('1e0') FROM %s t",
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::float(),
			'pgsqlExpectedType' => self::float(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield "SQRT('foo')" => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => "SELECT SQRT('foo') FROM %s t",
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::hasDbal4() ? self::mixed() : null, // fails in UDF: sqrt(): Argument #1 ($num) must be of type float, string given
			'pdoPgsqlExpectedType' => null, // Invalid text representation
			'pgsqlExpectedType' => null, // Invalid text representation
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(t.col_string)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(t.col_string) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::hasDbal4() ? self::mixed() : null, // fails in UDF: sqrt(): Argument #1 ($num) must be of type float, string given
			'pdoPgsqlExpectedType' => null, // undefined function
			'pgsqlExpectedType' => null, // undefined function
			'mssqlExpectedType' => null, // Error converting data type
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'SQRT(1.0)' => [
			'data' => self::dataSqrt(),
			'dqlTemplate' => 'SELECT SQRT(1.0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.000000000000000',
			'pgsqlExpectedResult' => '1.000000000000000',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COUNT(t)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COUNT(t) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::intNonNegative(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'SUBSELECT' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t1.col_int, (SELECT COUNT(t2.col_int) FROM ' . PlatformEntity::class . ' t2) FROM %s t1',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::int(),
			'mysqlExpectedResult' => 9,
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COUNT(t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COUNT(t.col_int) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::intNonNegative(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COUNT(t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COUNT(t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::intNonNegative(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COUNT(1)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COUNT(1) FROM %s t',
			'mysqlExpectedType' => self::intNonNegative(),
			'sqliteExpectedType' => self::intNonNegative(),
			'pdoPgsqlExpectedType' => self::intNonNegative(),
			'pgsqlExpectedType' => self::intNonNegative(),
			'mssqlExpectedType' => self::intNonNegative(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 't.col_mixed' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT t.col_mixed FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'INT_PI()' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT INT_PI() FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::int(),
			'mysqlExpectedResult' => 3,
			'sqliteExpectedResult' => 3,
			'pdoPgsqlExpectedResult' => 3,
			'pgsqlExpectedResult' => 3,
			'mssqlExpectedResult' => 3,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield '-INT_PI()' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT -INT_PI() FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '-3.14159',
			'sqliteExpectedResult' => -3.14159,
			'pdoPgsqlExpectedResult' => '-3.14159',
			'pgsqlExpectedResult' => '-3.14159',
			'mssqlExpectedResult' => '-3.14159',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'BOOL_PI()' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT BOOL_PI() FROM %s t',
			'mysqlExpectedType' => self::bool(),
			'sqliteExpectedType' => self::bool(),
			'pdoPgsqlExpectedType' => self::bool(),
			'pgsqlExpectedType' => self::bool(),
			'mssqlExpectedType' => self::bool(),
			'mysqlExpectedResult' => true,
			'sqliteExpectedResult' => true,
			'pdoPgsqlExpectedResult' => true,
			'pgsqlExpectedResult' => true,
			'mssqlExpectedResult' => true,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'STRING_PI()' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT STRING_PI() FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '3.14159',
			'sqliteExpectedResult' => 3.14159,
			'pdoPgsqlExpectedResult' => '3.14159',
			'pgsqlExpectedResult' => '3.14159',
			'mssqlExpectedResult' => '3.14159',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'INT_WRAP(MIN(t.col_float)) + no data' => [
			'data' => self::dataNone(),
			'dqlTemplate' => 'SELECT INT_WRAP(MIN(t.col_float)) FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::intOrNull(),
			'mysqlExpectedResult' => null,
			'sqliteExpectedResult' => null,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'INT_WRAP(MIN(t.col_float))' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT INT_WRAP(MIN(t.col_float)) FROM %s t',
			'mysqlExpectedType' => self::intOrNull(),
			'sqliteExpectedType' => self::intOrNull(),
			'pdoPgsqlExpectedType' => self::intOrNull(),
			'pgsqlExpectedType' => self::intOrNull(),
			'mssqlExpectedType' => self::intOrNull(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_NONE,
		];

		yield 'COALESCE(t.col_datetime, t.col_datetime)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_datetime, t.col_datetime) FROM %s t',
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => self::string(),
			'pdoPgsqlExpectedType' => self::string(),
			'pgsqlExpectedType' => self::string(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '2024-01-31 12:59:59',
			'sqliteExpectedResult' => '2024-01-31 12:59:59',
			'pdoPgsqlExpectedResult' => '2024-01-31 12:59:59',
			'pgsqlExpectedResult' => '2024-01-31 12:59:59',
			'mssqlExpectedResult' => '2024-01-31 12:59:59.000000', // doctrine/dbal changes default ReturnDatesAsStrings to true
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(SUM(t.col_int_nullable), 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(SUM(t.col_int_nullable), 0) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericString(true, true), self::int()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(true, true), self::int()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0',
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(SUM(ABS(t.col_int)), 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(SUM(ABS(t.col_int)), 0) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'pgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9',
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => 9,
			'pgsqlExpectedResult' => 9,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_int_nullable, 'foo')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT COALESCE(t.col_int_nullable, 'foo') FROM %s t",
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::string()),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => null, // Conversion failed
			'mysqlExpectedResult' => 'foo',
			'sqliteExpectedResult' => 'foo',
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_int, 'foo')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT COALESCE(t.col_int, 'foo') FROM %s t",
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::string()),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9',
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_bool, 'foo')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT COALESCE(t.col_bool, 'foo') FROM %s t",
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::string()),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(1, 'foo')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT COALESCE(1, 'foo') FROM %s t",
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::string()),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(1, '1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT COALESCE(1, '1') FROM %s t",
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'pgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(1, 1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(1, 1.0) FROM %s t',
			'mysqlExpectedType' => self::numericString(),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::float()),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'pgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(0, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(0, 0) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(1.0, 1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(1.0, 1.0) FROM %s t',
			'mysqlExpectedType' => self::numericString(true, true),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1.0',
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1.0',
			'pgsqlExpectedResult' => '1.0',
			'mssqlExpectedResult' => '1.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(1e0, 1.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(1e0, 1.0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => self::numericString(true, true),
			'pgsqlExpectedType' => self::numericString(true, true),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1.0,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(1, 1.0, 1e0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(1, 1.0, 1e0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'pgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(1, 1.0, 1e0, '1')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => "SELECT COALESCE(1, 1.0, 1e0, '1') FROM %s t",
			'mysqlExpectedType' => self::numericString(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int(), self::numericString(true, true)),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'pgsqlExpectedType' => TypeCombinator::union(self::int(), self::numericString(true, true)),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '1',
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => '1',
			'pgsqlExpectedResult' => '1',
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, 0) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => 0,
			'pgsqlExpectedResult' => 0,
			'mssqlExpectedResult' => 0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_bool)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_bool) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_float_nullable, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_float_nullable, 0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'pdoPgsqlExpectedType' => PHP_VERSION_ID < 80400
				? TypeCombinator::union(self::numericString(), self::int())
				: TypeCombinator::union(self::float(), self::int()),
			'pgsqlExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => PHP_VERSION_ID < 80400 ? '0' : 0.0,
			'pgsqlExpectedResult' => 0.0,
			'mssqlExpectedResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_float_nullable, 0.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_float_nullable, 0.0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => self::float(),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::float(), self::numericString(false, true)),
			'pgsqlExpectedType' => TypeCombinator::union(self::float(), self::numericString(false, true)),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => 0.0,
			'pgsqlExpectedResult' => 0.0,
			'mssqlExpectedResult' => 0.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, 0) FROM %s t',
			'mysqlExpectedType' => self::numericString(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'pdoPgsqlExpectedType' => TypeCombinator::union(self::numericString(), self::int()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(), self::int()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0.0',
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => '0',
			'pgsqlExpectedResult' => '0',
			'mssqlExpectedResult' => '.0',
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'pdoPgsqlExpectedType' => PHP_VERSION_ID < 80400
				? TypeCombinator::union(self::numericString(), self::int())
				: TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0,
			'pdoPgsqlExpectedResult' => PHP_VERSION_ID < 80400 ? '0' : 0.0,
			'pgsqlExpectedResult' => 0.0,
			'mssqlExpectedResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0.0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0.0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'pdoPgsqlExpectedType' => PHP_VERSION_ID < 80400
				? TypeCombinator::union(self::numericString(), self::int())
				: TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => PHP_VERSION_ID < 80400 ? '0' : 0.0,
			'pgsqlExpectedResult' => 0.0,
			'mssqlExpectedResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0e0)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, 0e0) FROM %s t',
			'mysqlExpectedType' => self::float(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int()),
			'pdoPgsqlExpectedType' => PHP_VERSION_ID < 80400
				? TypeCombinator::union(self::numericString(), self::int())
				: TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 0.0,
			'sqliteExpectedResult' => 0.0,
			'pdoPgsqlExpectedResult' => PHP_VERSION_ID < 80400 ? '0' : 0.0,
			'pgsqlExpectedResult' => 0.0,
			'mssqlExpectedResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield "COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, '0')" => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, \'0\') FROM %s t',
			'mysqlExpectedType' => self::numericString(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int(), self::numericString()),
			'pdoPgsqlExpectedType' => PHP_VERSION_ID < 80400
				? TypeCombinator::union(self::numericString(), self::int())
				: TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'pgsqlExpectedType' => TypeCombinator::union(self::numericString(), self::int(), self::float()),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '0',
			'sqliteExpectedResult' => '0',
			'pdoPgsqlExpectedResult' => PHP_VERSION_ID < 80400 ? '0' : 0.0,
			'pgsqlExpectedResult' => 0.0,
			'mssqlExpectedResult' => 0.0,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, t.col_string)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, t.col_string) FROM %s t',
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => TypeCombinator::union(self::float(), self::int(), self::string()),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => null, // Error converting data
			'mysqlExpectedResult' => 'foobar',
			'sqliteExpectedResult' => 'foobar',
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => null,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, t.col_mixed)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_int_nullable, t.col_decimal_nullable, t.col_float_nullable, t.col_mixed) FROM %s t',
			'mysqlExpectedType' => self::mixed(),
			'sqliteExpectedType' => self::mixed(),
			'pdoPgsqlExpectedType' => self::mixed(),
			'pgsqlExpectedType' => self::mixed(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1.0,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1.0,
			'pgsqlExpectedResult' => 1.0,
			'mssqlExpectedResult' => 1.0,
			'stringify' => self::STRINGIFY_PG_FLOAT,
		];

		yield 'COALESCE(t.col_string_nullable, t.col_int)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT COALESCE(t.col_string_nullable, t.col_int) FROM %s t',
			'mysqlExpectedType' => self::string(),
			'sqliteExpectedType' => TypeCombinator::union(self::int(), self::string()),
			'pdoPgsqlExpectedType' => null, // COALESCE types cannot be matched
			'pgsqlExpectedType' => null, // COALESCE types cannot be matched
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => '9',
			'sqliteExpectedResult' => 9,
			'pdoPgsqlExpectedResult' => null,
			'pgsqlExpectedResult' => null,
			'mssqlExpectedResult' => 9,
			'stringify' => self::STRINGIFY_DEFAULT,
		];

		yield 'IDENTITY(t.related_entity)' => [
			'data' => self::dataDefault(),
			'dqlTemplate' => 'SELECT IDENTITY(t.related_entity) FROM %s t',
			'mysqlExpectedType' => self::int(),
			'sqliteExpectedType' => self::int(),
			'pdoPgsqlExpectedType' => self::int(),
			'pgsqlExpectedType' => self::int(),
			'mssqlExpectedType' => self::mixed(),
			'mysqlExpectedResult' => 1,
			'sqliteExpectedResult' => 1,
			'pdoPgsqlExpectedResult' => 1,
			'pgsqlExpectedResult' => 1,
			'mssqlExpectedResult' => 1,
			'stringify' => self::STRINGIFY_DEFAULT,
		];
	}

	/**
	 * @param mixed $expectedFirstResult
	 * @param array<string, mixed> $data
	 * @param self::STRINGIFY_* $stringification
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
		bool $useUnknownDriverForInference = false
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

			if ($useUnknownDriverForInference) {
				$query = $this->cloneQueryAndInjectConnectionWithUnknownPdoMysqlDriver($query);
			}

			$inferredType = $this->getInferredType($query);

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
				$inferredType->describe(VerbosityLevel::precise()),
			));
		}

		$driverDetector = new DriverDetector();
		$driverType = $driverDetector->detect($connection);

		$stringify = $this->shouldStringify($stringification, $driverType, $phpVersion, $configName);
		if (
			$stringify
			&& !$useUnknownDriverForInference // do not stringify, we already passed union with stringified one above
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
	private function getInferredType(Query $query): Type
	{
		$typeBuilder = new QueryResultTypeBuilder();
		$phpVersion = new PhpVersion(PHP_VERSION_ID); // @phpstan-ignore-line ctor not in bc promise
		QueryResultTypeWalker::walk(
			$query,
			$typeBuilder,
			self::getContainer()->getByType(DescriptorRegistry::class),
			$phpVersion,
			new DriverDetector(),
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
					$realFirstResult,
				),
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
				$expectedFirstResultExported,
			),
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
				$realType->describe(VerbosityLevel::precise()),
			),
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
		$inferredFirstItemType = $inferredType->getIterableValueType();

		self::assertTrue(
			$expectedFirstItemType->accepts($inferredFirstItemType, true)->yes(),
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
				$expectedFirstItemType->describe(VerbosityLevel::precise()),
			),
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

	private static function numericString(bool $lowercase = false, bool $uppercase = false): Type
	{
		$types = [
			new StringType(),
			new AccessoryNumericStringType(),
		];
		if ($lowercase) {
			$types[] = new AccessoryLowercaseStringType();
		}
		if ($uppercase) {
			$types[] = new AccessoryUppercaseStringType();
		}

		return new IntersectionType($types);
	}

	private static function string(): Type
	{
		return new StringType();
	}

	private static function numericStringOrNull(bool $lowercase = false, bool $uppercase = false): Type
	{
		return TypeCombinator::addNull(self::numericString($lowercase, $uppercase));
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

	private function resolveDefaultFloatStringification(?string $driver, int $php, string $configName): bool
	{
		if ($php < 80400 && $driver === DriverDetector::PDO_PGSQL) {
			return true; // pdo_pgsql does stringify floats even without ATTR_STRINGIFY_FETCHES prior to PHP 8.4
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

		if ($stringification === self::STRINGIFY_PG_FLOAT) {
			return $this->resolveDefaultFloatStringification($driverType, $phpVersion, $configName);
		}

		throw new LogicException('Unknown stringification: ' . $stringification);
	}

	/**
	 * @param Query<mixed> $query
	 * @return Query<mixed>
	 */
	private function cloneQueryAndInjectConnectionWithUnknownPdoMysqlDriver(Query $query): Query
	{
		if ($query->getDQL() === null) {
			throw new LogicException('Query does not have DQL');
		}

		$connection = DriverManager::getConnection([
			'driverClass' => UnknownDriver::class,
			'serverVersion' => $this->getSampleServerVersionForDriver('pdo_mysql'),
		]);

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
		$config->addCustomStringFunction('INT_WRAP', TypedExpressionIntegerWrapFunction::class);

		return $config;
	}

	private function determineTypeForUnknownDriverUnknownSetup(Type $originalExpectedType, string $stringify): Type
	{
		if ($stringify === self::STRINGIFY_NONE) {
			return $originalExpectedType; // those are direct column fetches, those always work (this is mild abuse of this flag)
		}

		return new MixedType();
	}

}
