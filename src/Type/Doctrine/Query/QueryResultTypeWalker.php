<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use BackedEnum;
use Doctrine\DBAL\Types\StringType as DbalStringType;
use Doctrine\DBAL\Types\Type as DbalType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\TypedExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\SqlWalker;
use PDO;
use PHPStan\Doctrine\Driver\DriverDetector;
use PHPStan\Php\PhpVersion;
use PHPStan\ShouldNotHappenException;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDriverAwareDescriptor;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NeverType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;
use PHPStan\Type\TypeUtils;
use PHPStan\Type\UnionType;
use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function get_class;
use function gettype;
use function in_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function serialize;
use function sprintf;
use function stripos;
use function strpos;
use function strtolower;
use function strtoupper;
use function unserialize;

/**
 * QueryResultTypeWalker is a TreeWalker that uses a QueryResultTypeBuilder to build the result type of a Query
 *
 * It extends SqlkWalker because AST\Node::dispatch() accepts SqlWalker only
 *
 * @phpstan-import-type QueryComponent from Parser
 */
class QueryResultTypeWalker extends SqlWalker
{

	private const HINT_TYPE_MAPPING = self::class . '::HINT_TYPE_MAPPING';

	private const HINT_DESCRIPTOR_REGISTRY = self::class . '::HINT_DESCRIPTOR_REGISTRY';

	private const HINT_PHP_VERSION = self::class . '::HINT_PHP_VERSION';

	private const HINT_DRIVER_DETECTOR = self::class . '::HINT_DRIVER_DETECTOR';

	/**
	 * Counter for generating unique scalar result.
	 *
	 */
	private int $scalarResultCounter = 1;

	/**
	 * Counter for generating indexes.
	 *
	 */
	private int $newObjectCounter = 0;

	/** @var Query<mixed> */
	private Query $query;

	private EntityManagerInterface $em;

	private PhpVersion $phpVersion;

	/** @var DriverDetector::*|null */
	private $driverType;

	/** @var array<mixed> */
	private array $driverOptions;

	/**
	 * Map of all components/classes that appear in the DQL query.
	 *
	 * @var array<array-key,QueryComponent> $queryComponents
	 */
	private array $queryComponents;

	/** @var array<array-key,bool> */
	private array $nullableQueryComponents;

	private QueryResultTypeBuilder $typeBuilder;

	private DescriptorRegistry $descriptorRegistry;

	private bool $hasAggregateFunction;

	private bool $hasGroupByClause;


	/**
	 * @param Query<mixed> $query
	 */
	public static function walk(
		Query $query,
		QueryResultTypeBuilder $typeBuilder,
		DescriptorRegistry $descriptorRegistry,
		PhpVersion $phpVersion,
		DriverDetector $driverDetector
	): void
	{
		$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, self::class);
		$query->setHint(Query::HINT_CUSTOM_TREE_WALKERS, [QueryAggregateFunctionDetectorTreeWalker::class]);
		$query->setHint(self::HINT_TYPE_MAPPING, $typeBuilder);
		$query->setHint(self::HINT_DESCRIPTOR_REGISTRY, $descriptorRegistry);
		$query->setHint(self::HINT_PHP_VERSION, $phpVersion);
		$query->setHint(self::HINT_DRIVER_DETECTOR, $driverDetector);

		$parser = new Parser($query);
		$parser->parse();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Query<mixed> $query
	 * @param ParserResult $parserResult
	 * @param array<QueryComponent> $queryComponents
	 */
	public function __construct($query, $parserResult, array $queryComponents)
	{
		$this->query = $query;
		$this->em = $query->getEntityManager();
		$this->queryComponents = $queryComponents;
		$this->nullableQueryComponents = [];
		$this->hasAggregateFunction = $query->hasHint(QueryAggregateFunctionDetectorTreeWalker::HINT_HAS_AGGREGATE_FUNCTION);
		$this->hasGroupByClause = false;

		// The object is instantiated by Doctrine\ORM\Query\Parser, so receiving
		// dependencies through the constructor is not an option. Instead, we
		// receive the dependencies via query hints.

		$typeBuilder = $this->query->getHint(self::HINT_TYPE_MAPPING);

		if (!$typeBuilder instanceof QueryResultTypeBuilder) {
			throw new ShouldNotHappenException(sprintf(
				'Expected the query hint %s to contain a %s, but got a %s',
				self::HINT_TYPE_MAPPING,
				QueryResultTypeBuilder::class,
				is_object($typeBuilder) ? get_class($typeBuilder) : gettype($typeBuilder),
			));
		}

		$this->typeBuilder = $typeBuilder;

		$descriptorRegistry = $this->query->getHint(self::HINT_DESCRIPTOR_REGISTRY);

		if (!$descriptorRegistry instanceof DescriptorRegistry) {
			throw new ShouldNotHappenException(sprintf(
				'Expected the query hint %s to contain a %s, but got a %s',
				self::HINT_DESCRIPTOR_REGISTRY,
				DescriptorRegistry::class,
				is_object($descriptorRegistry) ? get_class($descriptorRegistry) : gettype($descriptorRegistry),
			));
		}

		$this->descriptorRegistry = $descriptorRegistry;

		$phpVersion = $this->query->getHint(self::HINT_PHP_VERSION);

		if (!$phpVersion instanceof PhpVersion) { // @phpstan-ignore-line ignore bc promise
			throw new ShouldNotHappenException(sprintf(
				'Expected the query hint %s to contain a %s, but got a %s',
				self::HINT_PHP_VERSION,
				PhpVersion::class,
				is_object($phpVersion) ? get_class($phpVersion) : gettype($phpVersion),
			));
		}

		$this->phpVersion = $phpVersion;

		$driverDetector = $this->query->getHint(self::HINT_DRIVER_DETECTOR);

		if (!$driverDetector instanceof DriverDetector) {
			throw new ShouldNotHappenException(sprintf(
				'Expected the query hint %s to contain a %s, but got a %s',
				self::HINT_DRIVER_DETECTOR,
				DriverDetector::class,
				is_object($driverDetector) ? get_class($driverDetector) : gettype($driverDetector),
			));
		}
		$connection = $this->em->getConnection();

		$this->driverType = $driverDetector->detect($connection);
		$this->driverOptions = $driverDetector->detectDriverOptions($connection);

		parent::__construct($query, $parserResult, $queryComponents);
	}

	public function walkSelectStatement(AST\SelectStatement $AST): string
	{
		$this->typeBuilder->setSelectQuery();
		$this->hasGroupByClause = $AST->groupByClause !== null;

		$this->walkFromClause($AST->fromClause);

		foreach ($AST->selectClause->selectExpressions as $selectExpression) {
			assert($selectExpression instanceof AST\Node);

			$selectExpression->dispatch($this);
		}

		return '';
	}

	public function walkUpdateStatement(AST\UpdateStatement $AST): string
	{
		return $this->marshalType(new MixedType());
	}

	public function walkDeleteStatement(AST\DeleteStatement $AST): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param string $identVariable
	 */
	public function walkEntityIdentificationVariable($identVariable): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param string      $identificationVariable
	 * @param string|null $fieldName
	 */
	public function walkIdentificationVariable($identificationVariable, $fieldName = null): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\PathExpression $pathExpr
	 */
	public function walkPathExpression($pathExpr): string
	{
		$fieldName = $pathExpr->field;
		$dqlAlias = $pathExpr->identificationVariable;
		$qComp = $this->queryComponents[$dqlAlias];
		assert(array_key_exists('metadata', $qComp));

		/** @var ClassMetadata<object> $class */
		$class = $qComp['metadata'];

		assert($fieldName !== null);

		switch ($pathExpr->type) {
			case AST\PathExpression::TYPE_STATE_FIELD:
				[$typeName, $enumType] = $this->getTypeOfField($class, $fieldName);

				$nullable = $this->isQueryComponentNullable($dqlAlias)
					|| $class->isNullable($fieldName)
					|| $this->hasAggregateWithoutGroupBy();

				$fieldType = $this->resolveDatabaseInternalType($typeName, $enumType, $nullable);

				return $this->marshalType($fieldType);

			case AST\PathExpression::TYPE_SINGLE_VALUED_ASSOCIATION:
				if (isset($class->associationMappings[$fieldName]['inherited'])) {
					/** @var class-string $newClassName */
					$newClassName = $class->associationMappings[$fieldName]['inherited'];
					$class = $this->em->getClassMetadata($newClassName);
				}

				$assoc = $class->associationMappings[$fieldName];

				if (
					!$assoc['isOwningSide']
					|| !isset($assoc['joinColumns'])
					|| count($assoc['joinColumns']) !== 1
				) {
					throw new ShouldNotHappenException();
				}

				$joinColumn = $assoc['joinColumns'][0];

				/** @var class-string $assocClassName */
				$assocClassName = $assoc['targetEntity'];

				$targetClass = $this->em->getClassMetadata($assocClassName);
				$identifierFieldNames = $targetClass->getIdentifierFieldNames();

				if (count($identifierFieldNames) !== 1) {
					throw new ShouldNotHappenException();
				}

				$targetFieldName = $identifierFieldNames[0];
				[$typeName, $enumType] = $this->getTypeOfField($targetClass, $targetFieldName);

				$nullable = ($joinColumn['nullable'] ?? true)
					|| $this->hasAggregateWithoutGroupBy();

				$fieldType = $this->resolveDatabaseInternalType($typeName, $enumType, $nullable);

				return $this->marshalType($fieldType);

			default:
				throw new ShouldNotHappenException();
		}
	}

	/**
	 * @param AST\SelectClause $selectClause
	 */
	public function walkSelectClause($selectClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\FromClause $fromClause
	 */
	public function walkFromClause($fromClause): string
	{
		foreach ($fromClause->identificationVariableDeclarations as $identificationVariableDecl) {
			assert($identificationVariableDecl instanceof AST\Node);

			$identificationVariableDecl->dispatch($this);
		}

		return '';
	}

	/**
	 * @param AST\IdentificationVariableDeclaration $identificationVariableDecl
	 */
	public function walkIdentificationVariableDeclaration($identificationVariableDecl): string
	{
		if ($identificationVariableDecl->indexBy !== null) {
			$identificationVariableDecl->indexBy->dispatch($this);
		}

		foreach ($identificationVariableDecl->joins as $join) {
			assert($join instanceof AST\Node);

			$join->dispatch($this);
		}

		return '';
	}

	/**
	 * @param AST\IndexBy $indexBy
	 */
	public function walkIndexBy($indexBy): void
	{
		$type = $this->unmarshalType($indexBy->singleValuedPathExpression->dispatch($this));
		$this->typeBuilder->setIndexedBy($type);
	}

	/**
	 * @param AST\RangeVariableDeclaration $rangeVariableDeclaration
	 */
	public function walkRangeVariableDeclaration($rangeVariableDeclaration): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\JoinAssociationDeclaration 								  $joinAssociationDeclaration
	 * @param int                            								  $joinType
	 * @param AST\ConditionalExpression|AST\Phase2OptimizableConditional|null $condExpr
	 */
	public function walkJoinAssociationDeclaration($joinAssociationDeclaration, $joinType = AST\Join::JOIN_TYPE_INNER, $condExpr = null): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\Functions\FunctionNode $function
	 */
	public function walkFunction($function): string
	{
		switch (true) {
			case $function instanceof AST\Functions\AvgFunction:
				return $this->marshalType($this->inferAvgFunction($function));

			case $function instanceof AST\Functions\MaxFunction:
			case $function instanceof AST\Functions\MinFunction:
				//                       mysql      sqlite   pdo_pgsql   pgsql
				//	col_float =>         float       float      string   float
				//  col_decimal =>       string  int|float      string  string
				//  col_int =>           int           int         int     int
				//  col_bigint =>        int           int         int     int
				//
				//	MIN(col_float) =>    float        float    string    float
				//  MIN(col_decimal) =>  string   int|float    string   string
				//  MIN(col_int) =>      int            int      int       int
				//  MIN(col_bigint) =>   int            int      int       int

				$exprType = $this->unmarshalType($function->getSql($this));
				$exprType = $this->generalizeConstantType($exprType, $this->hasAggregateWithoutGroupBy());
				return $this->marshalType($exprType); // retains underlying type

			case $function instanceof AST\Functions\SumFunction:
				return $this->marshalType($this->inferSumFunction($function));

			case $function instanceof AST\Functions\CountFunction:
				return $this->marshalType(IntegerRangeType::fromInterval(0, null));

			case $function instanceof AST\Functions\AbsFunction:
				//                       mysql      sqlite     pdo_pgsql     pgsql
				//	col_float =>         float       float        string     float
				//  col_decimal =>       string  int|float        string    string
				//  col_int =>           int           int           int       int
				//  col_bigint =>        int           int           int       int
				//
				//  ABS(col_float) =>    float       float        string     float
				//  ABS(col_decimal) =>  string  int|float        string    string
				//  ABS(col_int) =>      int           int           int       int
				//  ABS(col_bigint) =>   int           int           int       int
				//  ABS(col_string) =>   float        float            x         x

				$exprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->simpleArithmeticExpression));
				$exprType = $this->castStringLiteralForFloatExpression($exprType);
				$exprType = $this->generalizeConstantType($exprType, false);

				$exprTypeNoNull = TypeCombinator::removeNull($exprType);
				$nullable = $this->canBeNull($exprType);

				if ($exprTypeNoNull->isInteger()->yes()) {
					$nonNegativeInt = $this->createNonNegativeInteger($nullable);
					return $this->marshalType($nonNegativeInt);
				}

				if ($this->containsOnlyNumericTypes($exprTypeNoNull)) {
					if ($this->driverType === DriverDetector::PDO_PGSQL) {
						return $this->marshalType($this->createNumericString($nullable));
					}

					return $this->marshalType($exprType); // retains underlying type
				}

				return $this->marshalType(new MixedType());

			case $function instanceof AST\Functions\BitAndFunction:
			case $function instanceof AST\Functions\BitOrFunction:
				$firstExprType = $this->unmarshalType($function->firstArithmetic->dispatch($this));
				$secondExprType = $this->unmarshalType($function->secondArithmetic->dispatch($this));

				$type = IntegerRangeType::fromInterval(0, null);
				if ($this->canBeNull($firstExprType) || $this->canBeNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\ConcatFunction:
				$hasNull = false;

				foreach ($function->concatExpressions as $expr) {
					$type = $this->unmarshalType($expr->dispatch($this));
					$hasNull = $hasNull || $this->canBeNull($type);
				}

				$type = new StringType();
				if ($hasNull) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\CurrentDateFunction:
			case $function instanceof AST\Functions\CurrentTimeFunction:
			case $function instanceof AST\Functions\CurrentTimestampFunction:
				return $this->marshalType(new StringType());

			case $function instanceof AST\Functions\DateAddFunction:
			case $function instanceof AST\Functions\DateSubFunction:
				$dateExprType = $this->unmarshalType($function->firstDateExpression->dispatch($this));
				$intervalExprType = $this->unmarshalType($function->intervalExpression->dispatch($this));

				$type = new StringType();
				if ($this->canBeNull($dateExprType) || $this->canBeNull($intervalExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\DateDiffFunction:
				$date1ExprType = $this->unmarshalType($function->date1->dispatch($this));
				$date2ExprType = $this->unmarshalType($function->date2->dispatch($this));

				if ($this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
					$type = new FloatType();
				} else {
					$type = new IntegerType();
				}

				if ($this->canBeNull($date1ExprType) || $this->canBeNull($date2ExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\LengthFunction:
				$stringPrimaryType = $this->unmarshalType($function->stringPrimary->dispatch($this));

				$type = IntegerRangeType::fromInterval(0, null);
				if ($this->canBeNull($stringPrimaryType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\LocateFunction:
				$firstExprType = $this->unmarshalType($this->walkStringPrimary($function->firstStringPrimary));
				$secondExprType = $this->unmarshalType($this->walkStringPrimary($function->secondStringPrimary));

				$type = IntegerRangeType::fromInterval(0, null);
				if ($this->canBeNull($firstExprType) || $this->canBeNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\LowerFunction:
			case $function instanceof AST\Functions\TrimFunction:
			case $function instanceof AST\Functions\UpperFunction:
				$stringPrimaryType = $this->unmarshalType($function->stringPrimary->dispatch($this));

				$type = new StringType();
				if ($this->canBeNull($stringPrimaryType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\ModFunction:
				$firstExprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->firstSimpleArithmeticExpression));
				$secondExprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->secondSimpleArithmeticExpression));

				$union = TypeCombinator::union($firstExprType, $secondExprType);
				$unionNoNull = TypeCombinator::removeNull($union);

				if (!$unionNoNull->isInteger()->yes()) {
					return $this->marshalType(new MixedType()); // dont try to deal with non-integer chaos
				}

				$type = IntegerRangeType::fromInterval(0, null);

				if ($this->canBeNull($firstExprType) || $this->canBeNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				$isPgSql = $this->driverType === DriverDetector::PGSQL || $this->driverType === DriverDetector::PDO_PGSQL;
				$mayBeZero = !(new ConstantIntegerType(0))->isSuperTypeOf($secondExprType)->no();

				if (!$isPgSql && $mayBeZero) { // MOD(x, 0) returns NULL in non-strict platforms, fails in postgre
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($this->generalizeConstantType($type, false));

			case $function instanceof AST\Functions\SqrtFunction:
				//                       mysql      sqlite       pdo_pgsql  pgsql
				//	col_float =>         float      float        string     float
				//  col_decimal =>       string     float|int    string     string
				//  col_int =>           int        int          int        int
				//  col_bigint =>        int        int          int        int
				//
				//  SQRT(col_float) =>   float      float        string     float
				//  SQRT(col_decimal) => float      float        string     string
				//  SQRT(col_int) =>     float      float        string     float
				//  SQRT(col_bigint) =>  float      float        string     float

				$exprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->simpleArithmeticExpression));
				$exprTypeNoNull = TypeCombinator::removeNull($exprType);

				if (!$this->containsOnlyNumericTypes($exprTypeNoNull)) {
					return $this->marshalType(new MixedType()); // dont try to deal with non-numeric args
				}

				if ($this->driverType === DriverDetector::MYSQLI || $this->driverType === DriverDetector::PDO_MYSQL || $this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
					$type = new FloatType();

					$cannotBeNegative = $exprType->isSmallerThan(new ConstantIntegerType(0))->no();
					$canBeNegative = !$cannotBeNegative;
					if ($canBeNegative) {
						$type = TypeCombinator::addNull($type);
					}

				} elseif ($this->driverType === DriverDetector::PDO_PGSQL) {
					$type = new IntersectionType([
						new StringType(),
						new AccessoryNumericStringType(),
					]);

				} elseif ($this->driverType === DriverDetector::PGSQL) {
					$castedExprType = $this->castStringLiteralForNumericExpression($exprTypeNoNull);

					if ($castedExprType->isInteger()->yes() || $castedExprType->isFloat()->yes()) {
						$type = $this->createFloat(false);

					} elseif ($castedExprType->isNumericString()->yes()) {
						$type = $this->createNumericString(false);

					} else {
						$type = TypeCombinator::union($this->createFloat(false), $this->createNumericString(false));
					}

				} else {
					$type = new MixedType();
				}

				if ($this->canBeNull($exprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\SubstringFunction:
				$stringType = $this->unmarshalType($function->stringPrimary->dispatch($this));
				$firstExprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->firstSimpleArithmeticExpression));

				if ($function->secondSimpleArithmeticExpression !== null) {
					$secondExprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->secondSimpleArithmeticExpression));
				} else {
					$secondExprType = new IntegerType();
				}

				$type = new StringType();
				if ($this->canBeNull($stringType) || $this->canBeNull($firstExprType) || $this->canBeNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\IdentityFunction:
				$dqlAlias = $function->pathExpression->identificationVariable;
				$assocField = $function->pathExpression->field;
				$queryComp = $this->queryComponents[$dqlAlias];
				assert(array_key_exists('metadata', $queryComp));
				$class = $queryComp['metadata'];
				$assoc = $class->associationMappings[$assocField];

				/** @var class-string $assocClassName */
				$assocClassName = $assoc['targetEntity'];
				$targetClass = $this->em->getClassMetadata($assocClassName);

				if ($function->fieldMapping === null) {
					$identifierFieldNames = $targetClass->getIdentifierFieldNames();
					if (count($identifierFieldNames) === 0) {
						throw new ShouldNotHappenException();
					}

					$targetFieldName = $identifierFieldNames[0];
				} else {
					$targetFieldName = $function->fieldMapping;
				}

				$fieldMapping = $targetClass->fieldMappings[$targetFieldName] ?? null;
				if ($fieldMapping === null) {
					return $this->marshalType(new MixedType());
				}

				[$typeName, $enumType] = $this->getTypeOfField($targetClass, $targetFieldName);

				if (!isset($assoc['joinColumns'])) {
					return $this->marshalType(new MixedType());
				}

				$joinColumn = null;

				foreach ($assoc['joinColumns'] as $item) {
					if ($item['referencedColumnName'] === $fieldMapping['columnName']) {
						$joinColumn = $item;
						break;
					}
				}

				if ($joinColumn === null) {
					return $this->marshalType(new MixedType());
				}

				$nullable = ($joinColumn['nullable'] ?? true)
					|| $this->isQueryComponentNullable($dqlAlias)
					|| $this->hasAggregateWithoutGroupBy();

				$fieldType = $this->resolveDatabaseInternalType($typeName, $enumType, $nullable);

				return $this->marshalType($fieldType);

			default:
				return $this->marshalType(new MixedType());
		}
	}

	private function inferAvgFunction(AST\Functions\AvgFunction $function): Type
	{
		//                       mysql      sqlite   pdo_pgsql    pgsql
		//	col_float =>         float       float      string    float
		//  col_decimal =>       string  int|float      string   string
		//  col_int =>           int           int         int      int
		//  col_bigint =>        int           int         int      int
		//
		//  AVG(col_float) =>    float       float      string    float
		//  AVG(col_decimal) =>  string      float      string   string
		//  AVG(col_int) =>      string      float      string   string
		//  AVG(col_bigint) =>   string      float      string   string

		$exprType = $this->unmarshalType($function->getSql($this));
		$exprTypeNoNull = TypeCombinator::removeNull($exprType);
		$nullable = $this->canBeNull($exprType) || $this->hasAggregateWithoutGroupBy();

		if ($this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
			return $this->createFloat($nullable);
		}

		if ($this->driverType === DriverDetector::PDO_MYSQL || $this->driverType === DriverDetector::MYSQLI) {
			if ($exprTypeNoNull->isInteger()->yes()) {
				return $this->createNumericString($nullable);
			}

			if ($exprTypeNoNull->isString()->yes() && !$exprTypeNoNull->isNumericString()->yes()) {
				return $this->createFloat($nullable);
			}

			return $this->generalizeConstantType($exprType, $nullable);
		}

		if ($this->driverType === DriverDetector::PGSQL || $this->driverType === DriverDetector::PDO_PGSQL) {
			if ($exprTypeNoNull->isInteger()->yes()) {
				return $this->createNumericString($nullable);
			}

			return $this->generalizeConstantType($exprType, $nullable);
		}

		return new MixedType();
	}

	private function inferSumFunction(AST\Functions\SumFunction $function): Type
	{
		//                       mysql      sqlite   pdo_pgsql     pgsql
		//  col_float =>         float       float     string      float
		//  col_decimal =>       string  int|float     string     string
		//  col_int =>           int           int        int        int
		//  col_bigint =>        int           int        int        int
		//
		//  SUM(col_float) =>    float        float    string      float
		//  SUM(col_decimal) =>  string   int|float    string     string
		//  SUM(col_int) =>      string         int       int        int
		//  SUM(col_bigint) =>   string         int    string     string

		$exprType = $this->unmarshalType($function->getSql($this));
		$exprTypeNoNull = TypeCombinator::removeNull($exprType);
		$nullable = $this->canBeNull($exprType) || $this->hasAggregateWithoutGroupBy();

		if ($this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
			if ($exprTypeNoNull->isString()->yes() && !$exprTypeNoNull->isNumericString()->yes()) {
				return $this->createFloat($nullable);
			}

			return $this->generalizeConstantType($exprType, $nullable);
		}

		if ($this->driverType === DriverDetector::PDO_MYSQL || $this->driverType === DriverDetector::MYSQLI) {
			if ($exprTypeNoNull->isInteger()->yes()) {
				return $this->createNumericString($nullable);
			}

			if ($exprTypeNoNull->isString()->yes() && !$exprTypeNoNull->isNumericString()->yes()) {
				return $this->createFloat($nullable);
			}

			return $this->generalizeConstantType($exprType, $nullable);
		}

		if ($this->driverType === DriverDetector::PGSQL || $this->driverType === DriverDetector::PDO_PGSQL) {
			if ($exprTypeNoNull->isInteger()->yes()) {
				return TypeCombinator::union(
					$this->createInteger($nullable),
					$this->createNumericString($nullable),
				);
			}

			return $this->generalizeConstantType($exprType, $nullable);
		}

		return new MixedType();
	}

	private function createFloat(bool $nullable): Type
	{
		$float = new FloatType();
		return $nullable ? TypeCombinator::addNull($float) : $float;
	}

	private function createFloatOrInt(bool $nullable): Type
	{
		$union = TypeCombinator::union(
			new FloatType(),
			new IntegerType(),
		);
		return $nullable ? TypeCombinator::addNull($union) : $union;
	}

	private function createInteger(bool $nullable): Type
	{
		$integer = new IntegerType();
		return $nullable ? TypeCombinator::addNull($integer) : $integer;
	}

	private function createNonNegativeInteger(bool $nullable): Type
	{
		$integer = IntegerRangeType::fromInterval(0, null);
		return $nullable ? TypeCombinator::addNull($integer) : $integer;
	}

	private function createNumericString(bool $nullable): Type
	{
		$numericString = TypeCombinator::intersect(
			new StringType(),
			new AccessoryNumericStringType(),
		);

		return $nullable ? TypeCombinator::addNull($numericString) : $numericString;
	}

	private function createString(bool $nullable): Type
	{
		$string = new StringType();
		return $nullable ? TypeCombinator::addNull($string) : $string;
	}

	private function containsOnlyNumericTypes(
		Type ...$checkedTypes
	): bool
	{
		foreach ($checkedTypes as $checkedType) {
			if (!$this->containsOnlyTypes($checkedType, [new IntegerType(), new FloatType(), $this->createNumericString(false)])) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param list<Type> $allowedTypes
	 */
	private function containsOnlyTypes(
		Type $checkedType,
		array $allowedTypes
	): bool
	{
		$allowedType = TypeCombinator::union(...$allowedTypes);
		return $allowedType->isSuperTypeOf($checkedType)->yes();
	}

	/**
	 * E.g. to ensure SUM(1) is inferred as int, not 1
	 */
	private function generalizeConstantType(Type $type, bool $makeNullable): Type
	{
		$containsNull = $this->canBeNull($type);
		$typeNoNull = TypeCombinator::removeNull($type);

		if (!$typeNoNull->isConstantScalarValue()->yes()) {
			$result = $type;

		} elseif ($typeNoNull->isInteger()->yes()) {
			$result = $this->createInteger($containsNull);

		} elseif ($typeNoNull->isFloat()->yes()) {
			$result = $this->createFloat($containsNull);

		} elseif ($typeNoNull->isNumericString()->yes()) {
			$result = $this->createNumericString($containsNull);

		} elseif ($typeNoNull->isString()->yes()) {
			$result = $this->createString($containsNull);

		} else {
			$result = $type;
		}

		return $makeNullable ? TypeCombinator::addNull($result) : $result;
	}

	/**
	 * @param AST\OrderByClause $orderByClause
	 */
	public function walkOrderByClause($orderByClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\OrderByItem $orderByItem
	 */
	public function walkOrderByItem($orderByItem): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\HavingClause $havingClause
	 */
	public function walkHavingClause($havingClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\Join $join
	 */
	public function walkJoin($join): string
	{
		$joinType = $join->joinType;
		$joinDeclaration = $join->joinAssociationDeclaration;

		switch (true) {
			case $joinDeclaration instanceof AST\RangeVariableDeclaration:
				$dqlAlias = $joinDeclaration->aliasIdentificationVariable;

				$this->nullableQueryComponents[$dqlAlias] = $joinType === AST\Join::JOIN_TYPE_LEFT || $joinType === AST\Join::JOIN_TYPE_LEFTOUTER;

				break;
			case $joinDeclaration instanceof AST\JoinAssociationDeclaration:
				$dqlAlias = $joinDeclaration->aliasIdentificationVariable;

				$this->nullableQueryComponents[$dqlAlias] = $joinType === AST\Join::JOIN_TYPE_LEFT || $joinType === AST\Join::JOIN_TYPE_LEFTOUTER;

				break;
		}

		return '';
	}

	/**
	 * @param AST\CoalesceExpression $coalesceExpression
	 */
	public function walkCoalesceExpression($coalesceExpression): string
	{
		$rawTypes = [];
		$expressionTypes = [];
		$allTypesContainNull = true;

		foreach ($coalesceExpression->scalarExpressions as $expression) {
			if (!$expression instanceof AST\Node) {
				$expressionTypes[] = new MixedType();
				continue;
			}

			$rawType = $this->unmarshalType($expression->dispatch($this));
			$rawTypes[] = $rawType;

			$allTypesContainNull = $allTypesContainNull && $this->canBeNull($rawType);

			// Some drivers manipulate the types, lets avoid false positives by generalizing constant types
			// e.g. sqlsrv: "COALESCE returns the data type of value with the highest precedence"
			// e.g. mysql: COALESCE(1, 'foo') === '1' (undocumented? https://gist.github.com/jrunning/4535434)
			$expressionTypes[] = $this->generalizeConstantType($rawType, false);
		}

		$generalizedUnion = TypeCombinator::union(...$expressionTypes);

		if (!$allTypesContainNull) {
			$generalizedUnion = TypeCombinator::removeNull($generalizedUnion);
		}

		if ($this->driverType === DriverDetector::MYSQLI || $this->driverType === DriverDetector::PDO_MYSQL) {
			return $this->marshalType(
				$this->inferCoalesceForMySql($rawTypes, $generalizedUnion),
			);
		}

		return $this->marshalType($generalizedUnion);
	}

	/**
	 * @param list<Type> $rawTypes
	 */
	private function inferCoalesceForMySql(array $rawTypes, Type $originalResult): Type
	{
		$containsString = false;
		$containsFloat = false;
		$allIsNumericExcludingLiteralString = true;

		foreach ($rawTypes as $rawType) {
			$rawTypeNoNull = TypeCombinator::removeNull($rawType);
			$isLiteralString = $rawTypeNoNull instanceof DqlConstantStringType && $rawTypeNoNull->getOriginLiteralType() === AST\Literal::STRING;

			if (!$this->containsOnlyNumericTypes($rawTypeNoNull) || $isLiteralString) {
				$allIsNumericExcludingLiteralString = false;
			}

			if ($rawTypeNoNull->isString()->yes()) {
				$containsString = true;
			}

			if (!$rawTypeNoNull->isFloat()->yes()) {
				continue;
			}

			$containsFloat = true;
		}

		if ($containsFloat && $allIsNumericExcludingLiteralString) {
			return $this->simpleFloatify($originalResult);
		} elseif ($containsString) {
			return $this->simpleStringify($originalResult);
		}

		return $originalResult;
	}

	/**
	 * @param AST\NullIfExpression $nullIfExpression
	 */
	public function walkNullIfExpression($nullIfExpression): string
	{
		$firstExpression = $nullIfExpression->firstExpression;

		if (!$firstExpression instanceof AST\Node) {
			return $this->marshalType(new MixedType());
		}

		$firstType = $this->unmarshalType($firstExpression->dispatch($this));

		// NULLIF() returns the first expression or NULL
		$type = TypeCombinator::addNull($firstType);

		return $this->marshalType($type);
	}

	public function walkGeneralCaseExpression(AST\GeneralCaseExpression $generalCaseExpression): string
	{
		$whenClauses = $generalCaseExpression->whenClauses;
		$elseScalarExpression = $generalCaseExpression->elseScalarExpression;
		$types = [];

		foreach ($whenClauses as $clause) {
			if (!$clause instanceof AST\WhenClause) {
				$types[] = new MixedType();
				continue;
			}

			$thenScalarExpression = $clause->thenScalarExpression;
			if (!$thenScalarExpression instanceof AST\Node) {
				$types[] = new MixedType();
				continue;
			}

			$types[] = $this->unmarshalType(
				$thenScalarExpression->dispatch($this),
			);
		}

		if ($elseScalarExpression instanceof AST\Node) {
			$types[] = $this->unmarshalType(
				$elseScalarExpression->dispatch($this),
			);
		}

		$type = TypeCombinator::union(...$types);

		return $this->marshalType($type);
	}

	/**
	 * @param AST\SimpleCaseExpression $simpleCaseExpression
	 */
	public function walkSimpleCaseExpression($simpleCaseExpression): string
	{
		$whenClauses = $simpleCaseExpression->simpleWhenClauses;
		$elseScalarExpression = $simpleCaseExpression->elseScalarExpression;
		$types = [];

		foreach ($whenClauses as $clause) {
			if (!$clause instanceof AST\SimpleWhenClause) {
				$types[] = new MixedType();
				continue;
			}

			$thenScalarExpression = $clause->thenScalarExpression;
			if (!$thenScalarExpression instanceof AST\Node) {
				$types[] = new MixedType();
				continue;
			}

			$types[] = $this->unmarshalType(
				$thenScalarExpression->dispatch($this),
			);
		}

		if ($elseScalarExpression instanceof AST\Node) {
			$types[] = $this->unmarshalType(
				$elseScalarExpression->dispatch($this),
			);
		}

		$type = TypeCombinator::union(...$types);

		return $this->marshalType($type);
	}

	/**
	 * @param AST\SelectExpression $selectExpression
	 */
	public function walkSelectExpression($selectExpression): string
	{
		$expr = $selectExpression->expression;
		$hidden = $selectExpression->hiddenAliasResultVariable;

		if ($hidden) {
			return '';
		}

		if (is_string($expr)) {
			$dqlAlias = $expr;
			$queryComp = $this->queryComponents[$dqlAlias];
			assert(array_key_exists('metadata', $queryComp));
			$class = $queryComp['metadata'];
			$resultAlias = $selectExpression->fieldIdentificationVariable ?? $dqlAlias;

			assert(array_key_exists('parent', $queryComp));
			if ($queryComp['parent'] !== null) {
				return '';
			}

			$type = new ObjectType($class->name);

			if ($this->isQueryComponentNullable($dqlAlias) || $this->hasAggregateWithoutGroupBy()) {
				$type = TypeCombinator::addNull($type);
			}

			$this->typeBuilder->addEntity($resultAlias, $type, $selectExpression->fieldIdentificationVariable);

			return '';
		}

		if ($expr instanceof AST\PathExpression) {
			assert($expr->type === AST\PathExpression::TYPE_STATE_FIELD);

			$fieldName = $expr->field;

			assert($fieldName !== null);

			$resultAlias = $selectExpression->fieldIdentificationVariable ?? $fieldName;

			$dqlAlias = $expr->identificationVariable;
			$qComp = $this->queryComponents[$dqlAlias];
			assert(array_key_exists('metadata', $qComp));
			$class = $qComp['metadata'];

			[$typeName, $enumType] = $this->getTypeOfField($class, $fieldName);

			$nullable = $this->isQueryComponentNullable($dqlAlias)
				|| $class->isNullable($fieldName)
				|| $this->hasAggregateWithoutGroupBy();

			$type = $this->resolveDoctrineType($typeName, $enumType, $nullable);

			$this->typeBuilder->addScalar($resultAlias, $type);

			return '';
		}

		if ($expr instanceof AST\NewObjectExpression) {
			$resultAlias = $selectExpression->fieldIdentificationVariable ?? $this->newObjectCounter++;

			$type = $this->unmarshalType($this->walkNewObject($expr));
			$this->typeBuilder->addNewObject($resultAlias, $type);

			return '';
		}

		if ($expr instanceof AST\Node) {
			$resultAlias = $selectExpression->fieldIdentificationVariable ?? $this->scalarResultCounter++;
			$type = $this->unmarshalType($expr->dispatch($this));

			if (
				$expr instanceof TypedExpression
				&& !$expr->getReturnType() instanceof DbalStringType // StringType is no-op, so using TypedExpression with that does nothing
			) {
				$dbalTypeName = DbalType::getTypeRegistry()->lookupName($expr->getReturnType());
				$type = TypeCombinator::intersect( // e.g. count is typed as int, but we infer int<0, max>
					$type,
					$this->resolveDoctrineType($dbalTypeName, null, TypeCombinator::containsNull($type)),
				);

				if ($this->hasAggregateWithoutGroupBy() && !$expr instanceof AST\Functions\CountFunction) {
					$type = TypeCombinator::addNull($type);
				}

			} else {
				// Expressions default to Doctrine's StringType, whose
				// convertToPHPValue() is a no-op. So the actual type depends on
				// the driver and PHP version.

				$type = TypeTraverser::map($type, function (Type $type, callable $traverse): Type {
					if ($type instanceof UnionType || $type instanceof IntersectionType) {
						return $traverse($type);
					}

					if ($type instanceof IntegerType) {
						$stringify = $this->shouldStringifyExpressions($type);

						if ($stringify->yes()) {
							return $type->toString();
						} elseif ($stringify->maybe()) {
							return TypeCombinator::union($type->toString(), $type);
						}

						return $type;
					}

					if ($type instanceof FloatType) {
						$stringify = $this->shouldStringifyExpressions($type);

						// e.g. 1.0 on sqlite results to '1' with pdo_stringify on PHP 8.1, but '1.0' on PHP 8.0 with no setup
						// so we relax constant types and return just numeric-string to avoid those issues
						$stringifiedFloat = $this->createNumericString(false);

						if ($stringify->yes()) {
							return $stringifiedFloat;
						} elseif ($stringify->maybe()) {
							return TypeCombinator::union($stringifiedFloat, $type);
						}

						return $type;
					}

					if ($type instanceof BooleanType) {
						$stringify = $this->shouldStringifyExpressions($type);

						if ($stringify->yes()) {
							return $type->toInteger()->toString();
						} elseif ($stringify->maybe()) {
							return TypeCombinator::union($type->toInteger()->toString(), $type);
						}

						return $type;
					}
					return $traverse($type);
				});

				if (!$this->isSupportedDriver()) {
					$type = new MixedType(); // avoid guessing for unsupported drivers, there are too many differences
				}
			}

			$this->typeBuilder->addScalar($resultAlias, $type);

			return '';
		}

		return '';
	}

	/**
	 * @param AST\QuantifiedExpression $qExpr
	 */
	public function walkQuantifiedExpression($qExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\Subselect $subselect
	 */
	public function walkSubselect($subselect): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\SubselectFromClause $subselectFromClause
	 */
	public function walkSubselectFromClause($subselectFromClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\SimpleSelectClause $simpleSelectClause
	 */
	public function walkSimpleSelectClause($simpleSelectClause): string
	{
		return $this->marshalType(new MixedType());
	}

	public function walkParenthesisExpression(AST\ParenthesisExpression $parenthesisExpression): string
	{
		return $parenthesisExpression->expression->dispatch($this);
	}

	/**
	 * @param AST\NewObjectExpression $newObjectExpression
	 * @param string|null             $newObjectResultAlias
	 */
	public function walkNewObject($newObjectExpression, $newObjectResultAlias = null): string
	{
		for ($i = 0; $i < count($newObjectExpression->args); $i++) {
			$this->scalarResultCounter++;
		}

		$type = new ObjectType($newObjectExpression->className);

		return $this->marshalType($type);
	}

	/**
	 * @param AST\SimpleSelectExpression $simpleSelectExpression
	 */
	public function walkSimpleSelectExpression($simpleSelectExpression): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\AggregateExpression $aggExpression
	 */
	public function walkAggregateExpression($aggExpression): string
	{
		switch (strtoupper($aggExpression->functionName)) {
			case 'AVG':
			case 'SUM':
				$type = $this->unmarshalType($this->walkSimpleArithmeticExpression($aggExpression->pathExpression));
				$type = $this->castStringLiteralForNumericExpression($type);
				return $this->marshalType($type);

			case 'MAX':
			case 'MIN':
				return $this->walkSimpleArithmeticExpression($aggExpression->pathExpression);

			case 'COUNT':
				return $this->marshalType(IntegerRangeType::fromInterval(0, null));

			default:
				return $this->marshalType(new MixedType());
		}
	}

	private function castStringLiteralForFloatExpression(Type $type): Type
	{
		if (!$type instanceof DqlConstantStringType || $type->getOriginLiteralType() !== AST\Literal::STRING) {
			return $type;
		}

		$value = $type->getValue();

		if (is_numeric($value)) {
			return new ConstantFloatType((float) $value);
		}

		return $type;
	}

	/**
	 * Numeric strings are kept as strings in literal usage, but casted to numeric value once used in numeric expression
	 *  - SELECT '1'     => '1'
	 *  - SELECT 1 * '1' => 1
	 */
	private function castStringLiteralForNumericExpression(Type $type): Type
	{
		if (!$type instanceof DqlConstantStringType || $type->getOriginLiteralType() !== AST\Literal::STRING) {
			return $type;
		}

		$isMysql = $this->driverType === DriverDetector::MYSQLI || $this->driverType === DriverDetector::PDO_MYSQL;
		$value = $type->getValue();

		if (is_numeric($value)) {
			if (strpos($value, '.') === false && strpos($value, 'e') === false && !$isMysql) {
				return new ConstantIntegerType((int) $value);
			}

			return new ConstantFloatType((float) $value);
		}

		return $type;
	}

	/**
	 * @param AST\GroupByClause $groupByClause
	 */
	public function walkGroupByClause($groupByClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\PathExpression|string $groupByItem
	 */
	public function walkGroupByItem($groupByItem): string
	{
		return $this->marshalType(new MixedType());
	}

	public function walkDeleteClause(AST\DeleteClause $deleteClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\UpdateClause $updateClause
	 */
	public function walkUpdateClause($updateClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\UpdateItem $updateItem
	 */
	public function walkUpdateItem($updateItem): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\WhereClause|null $whereClause
	 */
	public function walkWhereClause($whereClause): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ConditionalExpression|AST\Phase2OptimizableConditional $condExpr
	 */
	public function walkConditionalExpression($condExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ConditionalTerm|AST\ConditionalPrimary|AST\ConditionalFactor $condTerm
	 */
	public function walkConditionalTerm($condTerm): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ConditionalFactor|AST\ConditionalPrimary $factor
	 */
	public function walkConditionalFactor($factor): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ConditionalPrimary $primary
	 */
	public function walkConditionalPrimary($primary): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ExistsExpression $existsExpr
	 */
	public function walkExistsExpression($existsExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\CollectionMemberExpression $collMemberExpr
	 */
	public function walkCollectionMemberExpression($collMemberExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\EmptyCollectionComparisonExpression $emptyCollCompExpr
	 */
	public function walkEmptyCollectionComparisonExpression($emptyCollCompExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\NullComparisonExpression $nullCompExpr
	 */
	public function walkNullComparisonExpression($nullCompExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param mixed $inExpr
	 */
	public function walkInExpression($inExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\InstanceOfExpression $instanceOfExpr
	 */
	public function walkInstanceOfExpression($instanceOfExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param mixed $inParam
	 */
	public function walkInParameter($inParam): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\Literal $literal
	 */
	public function walkLiteral($literal): string
	{
		switch ($literal->type) {
			case AST\Literal::STRING:
				$value = $literal->value;
				assert(is_string($value));
				$type = new DqlConstantStringType($value, $literal->type);
				break;

			case AST\Literal::BOOLEAN:
				$value = strtolower($literal->value) === 'true';
				if ($this->driverType === DriverDetector::PDO_PGSQL || $this->driverType === DriverDetector::PGSQL) {
					$type = new ConstantBooleanType($value);
				} else {
					$type = new ConstantIntegerType($value ? 1 : 0);
				}
				break;

			case AST\Literal::NUMERIC:
				$value = $literal->value;
				assert(is_int($value) || is_string($value)); // ensured in parser

				if (is_int($value) || (strpos($value, '.') === false && strpos($value, 'e') === false)) {
					$type = new ConstantIntegerType((int) $value);

				} else {
					if ($this->driverType === DriverDetector::PDO_MYSQL || $this->driverType === DriverDetector::MYSQLI) {
						// both pdo_mysql and mysqli hydrates decimal literal (e.g. 123.4) as string no matter the configuration (e.g. PDO::ATTR_STRINGIFY_FETCHES being false) and PHP version
						// the only way to force float is to use float literal with scientific notation (e.g. 123.4e0)
						// https://dev.mysql.com/doc/refman/8.0/en/number-literals.html

						if (stripos($value, 'e') !== false) {
							$type = new ConstantFloatType((float) $value);
						} else {
							$type = new DqlConstantStringType($value, $literal->type);
						}
					} elseif ($this->driverType === DriverDetector::PGSQL || $this->driverType === DriverDetector::PDO_PGSQL) {
						if (stripos($value, 'e') !== false) {
							$type = new DqlConstantStringType((string) (float) $value, $literal->type);
						} else {
							$type = new DqlConstantStringType($value, $literal->type);
						}

					} else {
						$type = new ConstantFloatType((float) $value);
					}
				}

				break;

			default:
				$type = new MixedType();
				break;
		}

		return $this->marshalType($type);
	}

	/**
	 * @param AST\BetweenExpression $betweenExpr
	 */
	public function walkBetweenExpression($betweenExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\LikeExpression $likeExpr
	 */
	public function walkLikeExpression($likeExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\PathExpression $stateFieldPathExpression
	 */
	public function walkStateFieldPathExpression($stateFieldPathExpression): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ComparisonExpression $compExpr
	 */
	public function walkComparisonExpression($compExpr): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\InputParameter $inputParam
	 */
	public function walkInputParameter($inputParam): string
	{
		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\ArithmeticExpression $arithmeticExpr
	 */
	public function walkArithmeticExpression($arithmeticExpr): string
	{
		if ($arithmeticExpr->simpleArithmeticExpression !== null) {
			return $this->walkSimpleArithmeticExpression($arithmeticExpr->simpleArithmeticExpression);
		}

		if ($arithmeticExpr->subselect !== null) {
			return $arithmeticExpr->subselect->dispatch($this);
		}

		return $this->marshalType(new MixedType());
	}

	/**
	 * @param AST\Node|string $simpleArithmeticExpr
	 */
	public function walkSimpleArithmeticExpression($simpleArithmeticExpr): string
	{
		if (!$simpleArithmeticExpr instanceof AST\SimpleArithmeticExpression) {
			return $this->walkArithmeticTerm($simpleArithmeticExpr);
		}

		$types = [];

		foreach ($simpleArithmeticExpr->arithmeticTerms as $term) {
			if (!$term instanceof AST\Node) {
				// Skip '+' or '-'
				continue;
			}

			$types[] = $this->castStringLiteralForNumericExpression(
				$this->unmarshalType($this->walkArithmeticPrimary($term)),
			);
		}

		return $this->marshalType($this->inferPlusMinusTimesType($types));
	}

	/**
	 * @param mixed $term
	 */
	public function walkArithmeticTerm($term): string
	{
		if (!$term instanceof AST\ArithmeticTerm) {
			return $this->walkArithmeticFactor($term);
		}

		$types = [];
		$operators = [];

		foreach ($term->arithmeticFactors as $factor) {
			if (!$factor instanceof AST\Node) {
				assert(is_string($factor));
				$operators[$factor] = $factor;
				continue; // Skip '*' or '/'
			}

			$types[] = $this->castStringLiteralForNumericExpression(
				$this->unmarshalType($this->walkArithmeticPrimary($factor)),
			);
		}

		if (array_values($operators) === ['*']) {
			return $this->marshalType($this->inferPlusMinusTimesType($types));
		}

		return $this->marshalType($this->inferDivisionType($types));
	}

	/**
	 * @param list<Type> $termTypes
	 */
	private function inferPlusMinusTimesType(array $termTypes): Type
	{
		//                             mysql        sqlite     pdo_pgsql  pgsql
		// col_float                   float        float      string     float
		// col_decimal                 string       float|int  string     string
		// col_int                     int          int        int        int
		// col_bigint                  int          int        int        int
		// col_bool                    int          int        bool       bool
		//
		// col_int + col_int           int          int        int        int
		// col_int + col_float         float        float      string     float
		// col_float + col_float       float        float      string     float
		// col_float + col_decimal     float        float      string     float
		// col_int + col_decimal       string       float|int  string     string
		// col_decimal + col_decimal   string       float|int  string     string
		// col_string + col_string     float        int        x          x
		// col_int + col_string        float        int        x          x
		// col_bool + col_bool         int          int        x          x
		// col_int + col_bool          int          int        x          x
		// col_float + col_string      float        float      x          x
		// col_decimal + col_string    float        float|int  x          x
		// col_float + col_bool        float        float      x          x
		// col_decimal + col_bool      string       float|int  x          x

		$types = [];
		$typesNoNull = [];

		foreach ($termTypes as $termType) {
			$generalizedType = $this->generalizeConstantType($termType, false);
			$types[] = $generalizedType;
			$typesNoNull[] = TypeCombinator::removeNull($generalizedType);
		}

		$union = TypeCombinator::union(...$types);
		$nullable = $this->canBeNull($union);
		$unionWithoutNull = TypeCombinator::removeNull($union);

		if ($unionWithoutNull->isInteger()->yes()) {
			return $this->createInteger($nullable);
		}

		if ($this->driverType === DriverDetector::PDO_PGSQL) {
			if ($this->containsOnlyNumericTypes($unionWithoutNull)) {
				return $this->createNumericString($nullable);
			}
		}

		if ($this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
			if (!$this->containsOnlyNumericTypes(...$typesNoNull)) {
				return new MixedType();
			}

			foreach ($typesNoNull as $typeNoNull) {
				if ($typeNoNull->isFloat()->yes()) {
					return $this->createFloat($nullable);
				}
			}

			return $this->createFloatOrInt($nullable);
		}

		if ($this->driverType === DriverDetector::MYSQLI || $this->driverType === DriverDetector::PDO_MYSQL || $this->driverType === DriverDetector::PGSQL) {
			if ($this->containsOnlyTypes($unionWithoutNull, [new IntegerType(), new FloatType()])) {
				return $this->createFloat($nullable);
			}

			if ($this->containsOnlyTypes($unionWithoutNull, [new IntegerType(), $this->createNumericString(false)])) {
				return $this->createNumericString($nullable);
			}

			if ($this->containsOnlyNumericTypes($unionWithoutNull)) {
				return $this->createFloat($nullable);
			}
		}

		return new MixedType();
	}

	/**
	 * @param list<Type> $termTypes
	 */
	private function inferDivisionType(array $termTypes): Type
	{
		//                            mysql      sqlite    pdo_pgsql     pgsql
		// col_float =>               float      float     string        float
		// col_decimal =>             string     float|int string        string
		// col_int =>                 int        int       int           int
		// col_bigint =>              int        int       int           int
		//
		// col_int / col_int          string     int       int           int
		// col_int / col_float        float      float     string        float
		// col_float / col_float      float      float     string        float
		// col_float / col_decimal    float      float     string        float
		// col_int / col_decimal      string     float|int string        string
		// col_decimal / col_decimal  string     float|int string        string
		// col_string / col_string    null       null      x             x
		// col_int / col_string       null       null      x             x
		// col_bool / col_bool        string     int       x             x
		// col_int / col_bool         string     int       x             x
		// col_float / col_string     null       null      x             x
		// col_decimal / col_string   null       null      x             x
		// col_float / col_bool       float      float     x             x
		// col_decimal / col_bool     string     float     x             x

		$types = [];
		$typesNoNull = [];

		foreach ($termTypes as $termType) {
			$generalizedType = $this->generalizeConstantType($termType, false);
			$types[] = $generalizedType;
			$typesNoNull[] = TypeCombinator::removeNull($generalizedType);
		}

		$union = TypeCombinator::union(...$types);
		$nullable = $this->canBeNull($union);
		$unionWithoutNull = TypeCombinator::removeNull($union);

		if ($unionWithoutNull->isInteger()->yes()) {
			if ($this->driverType === DriverDetector::MYSQLI || $this->driverType === DriverDetector::PDO_MYSQL) {
				return $this->createNumericString($nullable);
			} elseif ($this->driverType === DriverDetector::PDO_PGSQL || $this->driverType === DriverDetector::PGSQL || $this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
				return $this->createInteger($nullable);
			}

			return new MixedType();
		}

		if ($this->driverType === DriverDetector::PDO_PGSQL) {
			if ($this->containsOnlyTypes($unionWithoutNull, [new IntegerType(), new FloatType(), $this->createNumericString(false)])) {
				return $this->createNumericString($nullable);
			}
		}

		if ($this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::PDO_SQLITE) {
			if (!$this->containsOnlyNumericTypes(...$typesNoNull)) {
				return new MixedType();
			}

			foreach ($typesNoNull as $typeNoNull) {
				if ($typeNoNull->isFloat()->yes()) {
					return $this->createFloat($nullable);
				}
			}

			return $this->createFloatOrInt($nullable);
		}

		if ($this->driverType === DriverDetector::MYSQLI || $this->driverType === DriverDetector::PDO_MYSQL || $this->driverType === DriverDetector::PGSQL) {
			if ($this->containsOnlyTypes($unionWithoutNull, [new IntegerType(), new FloatType()])) {
				return $this->createFloat($nullable);
			}

			if ($this->containsOnlyTypes($unionWithoutNull, [new IntegerType(), $this->createNumericString(false)])) {
				return $this->createNumericString($nullable);
			}

			if ($this->containsOnlyTypes($unionWithoutNull, [new FloatType(), $this->createNumericString(false)])) {
				return $this->createFloat($nullable);
			}

			if ($this->containsOnlyNumericTypes($unionWithoutNull)) {
				return $this->createFloat($nullable);
			}
		}

		return new MixedType();
	}

	/**
	 * @param mixed $factor
	 */
	public function walkArithmeticFactor($factor): string
	{
		if (!$factor instanceof AST\ArithmeticFactor) {
			return $this->walkArithmeticPrimary($factor);
		}

		$primary = $factor->arithmeticPrimary;

		$type = $this->unmarshalType($this->walkArithmeticPrimary($primary));

		if ($type instanceof ConstantIntegerType && $factor->sign === false) {
			$type = new ConstantIntegerType($type->getValue() * -1);

		} elseif ($type instanceof IntegerRangeType && $factor->sign === false) {
			$type = IntegerRangeType::fromInterval(
				$type->getMax() === null ? null : $type->getMax() * -1,
				$type->getMin() === null ? null : $type->getMin() * -1,
			);

		} elseif ($type instanceof ConstantFloatType && $factor->sign === false) {
			$type = new ConstantFloatType($type->getValue() * -1);
		}

		return $this->marshalType($type);
	}

	/**
	 * @param mixed $primary
	 */
	public function walkArithmeticPrimary($primary): string
	{
		// ResultVariable (TODO)
		if (is_string($primary)) {
			return $this->marshalType(new MixedType());
		}

		if ($primary instanceof AST\Node) {
			return $primary->dispatch($this);
		}

		return $this->marshalType(new MixedType());
	}

	/**
	 * @param mixed $stringPrimary
	 */
	public function walkStringPrimary($stringPrimary): string
	{
		if ($stringPrimary instanceof AST\Node) {
			return $stringPrimary->dispatch($this);
		}

		return $this->marshalType(new MixedType());
	}

	/**
	 * @param string $resultVariable
	 */
	public function walkResultVariable($resultVariable): string
	{
		return $this->marshalType(new MixedType());
	}

	private function unmarshalType(string $marshalledType): Type
	{
		$type = unserialize($marshalledType);

		assert($type instanceof Type);

		return $type;
	}

	private function marshalType(Type $type): string
	{
		// TreeWalker methods are supposed to return string, so we need to
		// marshal the types in strings
		return serialize($type);
	}

	private function isQueryComponentNullable(string $dqlAlias): bool
	{
		return $this->nullableQueryComponents[$dqlAlias] ?? false;
	}

	/**
	 * @param ClassMetadata<object> $class
	 * @return array{string, ?class-string<BackedEnum>} Doctrine type name and enum type of field
	 */
	private function getTypeOfField(ClassMetadata $class, string $fieldName): array
	{
		assert(isset($class->fieldMappings[$fieldName]));

		$metadata = $class->fieldMappings[$fieldName];

		/** @var string $type */
		$type = $metadata['type'];

		/** @var class-string<BackedEnum>|null $enumType */
		$enumType = $metadata['enumType'] ?? null;

		if (!is_string($enumType) || !class_exists($enumType)) {
			$enumType = null;
		}

		return [$type, $enumType];
	}

	/** @param ?class-string<BackedEnum> $enumType */
	private function resolveDoctrineType(string $typeName, ?string $enumType = null, bool $nullable = false): Type
	{
		try {
			$type = $this->descriptorRegistry
				->get($typeName)
				->getWritableToPropertyType();

			if ($enumType !== null) {
				if ($type->isArray()->no()) {
					$type = new ObjectType($enumType);
				} else {
					$type = TypeCombinator::intersect(new ArrayType(
						$type->getIterableKeyType(),
						new ObjectType($enumType),
					), ...TypeUtils::getAccessoryTypes($type));
				}
			}
			if ($type instanceof NeverType) {
					$type = new MixedType();
			}
		} catch (DescriptorNotRegisteredException $e) {
			if ($enumType !== null) {
				$type = new ObjectType($enumType);
			} else {
				$type = new MixedType();
			}
		}

		if ($nullable) {
			$type = TypeCombinator::addNull($type);
		}

			return $type;
	}

	/** @param ?class-string<BackedEnum> $enumType */
	private function resolveDatabaseInternalType(string $typeName, ?string $enumType = null, bool $nullable = false): Type
	{
		try {
			$descriptor = $this->descriptorRegistry->get($typeName);
			$type = $descriptor instanceof DoctrineTypeDriverAwareDescriptor
				? $descriptor->getDatabaseInternalTypeForDriver($this->em->getConnection())
				: $descriptor->getDatabaseInternalType();

		} catch (DescriptorNotRegisteredException $e) {
			$type = new MixedType();
		}

		if ($enumType !== null) {
			$enumTypes = array_map(static fn ($enumType) => ConstantTypeHelper::getTypeFromValue($enumType->value), $enumType::cases());
			$enumType = TypeCombinator::union(...$enumTypes);
			$enumType = TypeCombinator::union($enumType, $enumType->toString());
			$type = TypeCombinator::intersect($enumType, $type);
		}

		if ($nullable) {
			$type = TypeCombinator::addNull($type);
		}

		return $type;
	}

	private function canBeNull(Type $type): bool
	{
		return !$type->isSuperTypeOf(new NullType())->no();
	}

	/**
	 * Returns whether the query has aggregate function and no group by clause
	 *
	 * Queries with aggregate functions and no group by clause always have
	 * exactly 1 group. This implies that they return exactly 1 row, and that
	 * all column can have a null value.
	 *
	 * c.f. SQL92, section 7.9, General Rules
	 */
	private function hasAggregateWithoutGroupBy(): bool
	{
		return $this->hasAggregateFunction && !$this->hasGroupByClause;
	}

	/**
	 * See analysis: https://github.com/janedbal/php-database-drivers-fetch-test
	 *
	 * Notable 8.1 changes:
	 * - pdo_mysql: https://github.com/php/php-src/commit/c18b1aea289e8ed6edb3f6e6a135018976a034c6
	 * - pdo_sqlite: https://github.com/php/php-src/commit/438b025a28cda2935613af412fc13702883dd3a2
	 * - pdo_pgsql: https://github.com/php/php-src/commit/737195c3ae6ac53b9501cfc39cc80fd462909c82
	 *
	 * @param IntegerType|FloatType|BooleanType $type
	 */
	private function shouldStringifyExpressions(Type $type): TrinaryLogic
	{
		if (in_array($this->driverType, [DriverDetector::PDO_MYSQL, DriverDetector::PDO_PGSQL, DriverDetector::PDO_SQLITE], true)) {
			$stringifyFetches = isset($this->driverOptions[PDO::ATTR_STRINGIFY_FETCHES]) ? (bool) $this->driverOptions[PDO::ATTR_STRINGIFY_FETCHES] : false;

			if ($this->driverType === DriverDetector::PDO_MYSQL) {
				$emulatedPrepares = isset($this->driverOptions[PDO::ATTR_EMULATE_PREPARES]) ? (bool) $this->driverOptions[PDO::ATTR_EMULATE_PREPARES] : true;

				if ($stringifyFetches) {
					return TrinaryLogic::createYes();
				}

				if ($this->phpVersion->getVersionId() >= 80100) {
					return TrinaryLogic::createNo();
				}

				if ($emulatedPrepares) {
					return TrinaryLogic::createYes();
				}

				return TrinaryLogic::createNo();
			}

			if ($this->driverType === DriverDetector::PDO_SQLITE) {
				if ($stringifyFetches) {
					return TrinaryLogic::createYes();
				}

				if ($this->phpVersion->getVersionId() >= 80100) {
					return TrinaryLogic::createNo();
				}

				return TrinaryLogic::createYes();
			}

			if ($this->driverType === DriverDetector::PDO_PGSQL) { // @phpstan-ignore-line always true, but keep it readable
				if ($type->isBoolean()->yes()) {
					if ($this->phpVersion->getVersionId() >= 80100) {
						return TrinaryLogic::createFromBoolean($stringifyFetches);
					}

					return TrinaryLogic::createNo();

				}

				return TrinaryLogic::createFromBoolean($stringifyFetches);
			}
		}

		if ($this->driverType === DriverDetector::PGSQL || $this->driverType === DriverDetector::SQLITE3 || $this->driverType === DriverDetector::MYSQLI) {
			return TrinaryLogic::createNo();
		}

		return TrinaryLogic::createMaybe();
	}

	private function isSupportedDriver(): bool
	{
		return in_array($this->driverType, [
			DriverDetector::MYSQLI,
			DriverDetector::PDO_MYSQL,
			DriverDetector::PGSQL,
			DriverDetector::PDO_PGSQL,
			DriverDetector::SQLITE3,
			DriverDetector::PDO_SQLITE,
		], true);
	}

	private function simpleStringify(Type $type): Type
	{
		return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
			if ($type instanceof UnionType || $type instanceof IntersectionType) {
				return $traverse($type);
			}

			if ($type instanceof IntegerType || $type instanceof FloatType || $type instanceof BooleanType) {
				return $type->toString();
			}

			return $traverse($type);
		});
	}

	private function simpleFloatify(Type $type): Type
	{
		return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
			if ($type instanceof UnionType || $type instanceof IntersectionType) {
				return $traverse($type);
			}

			if ($type instanceof IntegerType || $type instanceof BooleanType || $type instanceof StringType) {
				return $type->toFloat();
			}

			return $traverse($type);
		});
	}

}
