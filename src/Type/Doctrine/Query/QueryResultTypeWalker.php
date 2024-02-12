<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine\Query;

use BackedEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST;
use Doctrine\ORM\Query\AST\TypedExpression;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\SqlWalker;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\BooleanType;
use PHPStan\Type\Constant\ConstantFloatType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\ConstantTypeHelper;
use PHPStan\Type\Doctrine\DescriptorNotRegisteredException;
use PHPStan\Type\Doctrine\DescriptorRegistry;
use PHPStan\Type\FloatType;
use PHPStan\Type\GeneralizePrecision;
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
use function assert;
use function class_exists;
use function count;
use function floatval;
use function get_class;
use function gettype;
use function intval;
use function is_numeric;
use function is_object;
use function is_string;
use function serialize;
use function sprintf;
use function strtolower;
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

	/**
	 * Counter for generating unique scalar result.
	 *
	 * @var int
	 */
	private $scalarResultCounter = 1;

	/**
	 * Counter for generating indexes.
	 *
	 * @var int
	 */
	private $newObjectCounter = 0;

	/** @var Query<mixed> */
	private $query;

	/** @var EntityManagerInterface */
	private $em;

	/**
	 * Map of all components/classes that appear in the DQL query.
	 *
	 * @var array<array-key,QueryComponent> $queryComponents
	 */
	private $queryComponents;

	/** @var array<array-key,bool> */
	private $nullableQueryComponents;

	/** @var QueryResultTypeBuilder */
	private $typeBuilder;

	/** @var DescriptorRegistry */
	private $descriptorRegistry;

	/** @var bool */
	private $hasAggregateFunction;

	/** @var bool */
	private $hasGroupByClause;

	/**
	 * @param Query<mixed> $query
	 */
	public static function walk(Query $query, QueryResultTypeBuilder $typeBuilder, DescriptorRegistry $descriptorRegistry): void
	{
		$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, self::class);
		$query->setHint(self::HINT_TYPE_MAPPING, $typeBuilder);
		$query->setHint(self::HINT_DESCRIPTOR_REGISTRY, $descriptorRegistry);

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
		$this->hasAggregateFunction = false;
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
				is_object($typeBuilder) ? get_class($typeBuilder) : gettype($typeBuilder)
			));
		}

		$this->typeBuilder = $typeBuilder;

		$descriptorRegistry = $this->query->getHint(self::HINT_DESCRIPTOR_REGISTRY);

		if (!$descriptorRegistry instanceof DescriptorRegistry) {
			throw new ShouldNotHappenException(sprintf(
				'Expected the query hint %s to contain a %s, but got a %s',
				self::HINT_DESCRIPTOR_REGISTRY,
				DescriptorRegistry::class,
				is_object($descriptorRegistry) ? get_class($descriptorRegistry) : gettype($descriptorRegistry)
			));
		}

		$this->descriptorRegistry = $descriptorRegistry;

		parent::__construct($query, $parserResult, $queryComponents);
	}

	public function walkSelectStatement(AST\SelectStatement $AST): string
	{
		$this->typeBuilder->setSelectQuery();
		$this->hasAggregateFunction = $this->hasAggregateFunction($AST);
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
			case $function instanceof AST\Functions\MaxFunction:
			case $function instanceof AST\Functions\MinFunction:
			case $function instanceof AST\Functions\SumFunction:
			case $function instanceof AST\Functions\CountFunction:
				return $function->getSql($this);

			case $function instanceof AST\Functions\AbsFunction:
				$exprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->simpleArithmeticExpression));

				$type = TypeCombinator::union(
					IntegerRangeType::fromInterval(0, null),
					new FloatType()
				);

				if (TypeCombinator::containsNull($exprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\BitAndFunction:
			case $function instanceof AST\Functions\BitOrFunction:
				$firstExprType = $this->unmarshalType($function->firstArithmetic->dispatch($this));
				$secondExprType = $this->unmarshalType($function->secondArithmetic->dispatch($this));

				$type = IntegerRangeType::fromInterval(0, null);
				if (TypeCombinator::containsNull($firstExprType) || TypeCombinator::containsNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\ConcatFunction:
				$hasNull = false;

				foreach ($function->concatExpressions as $expr) {
					$type = $this->unmarshalType($expr->dispatch($this));
					$hasNull = $hasNull || TypeCombinator::containsNull($type);
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
				if (TypeCombinator::containsNull($dateExprType) || TypeCombinator::containsNull($intervalExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\DateDiffFunction:
				$date1ExprType = $this->unmarshalType($function->date1->dispatch($this));
				$date2ExprType = $this->unmarshalType($function->date2->dispatch($this));

				$type = TypeCombinator::union(
					new IntegerType(),
					new FloatType()
				);
				if (TypeCombinator::containsNull($date1ExprType) || TypeCombinator::containsNull($date2ExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\LengthFunction:
				$stringPrimaryType = $this->unmarshalType($function->stringPrimary->dispatch($this));

				$type = IntegerRangeType::fromInterval(0, null);
				if (TypeCombinator::containsNull($stringPrimaryType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\LocateFunction:
				$firstExprType = $this->unmarshalType($this->walkStringPrimary($function->firstStringPrimary));
				$secondExprType = $this->unmarshalType($this->walkStringPrimary($function->secondStringPrimary));

				$type = IntegerRangeType::fromInterval(0, null);
				if (TypeCombinator::containsNull($firstExprType) || TypeCombinator::containsNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\LowerFunction:
			case $function instanceof AST\Functions\TrimFunction:
			case $function instanceof AST\Functions\UpperFunction:
				$stringPrimaryType = $this->unmarshalType($function->stringPrimary->dispatch($this));

				$type = new StringType();
				if (TypeCombinator::containsNull($stringPrimaryType)) {
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\ModFunction:
				$firstExprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->firstSimpleArithmeticExpression));
				$secondExprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->secondSimpleArithmeticExpression));

				$type = IntegerRangeType::fromInterval(0, null);
				if (TypeCombinator::containsNull($firstExprType) || TypeCombinator::containsNull($secondExprType)) {
					$type = TypeCombinator::addNull($type);
				}

				if ((new ConstantIntegerType(0))->isSuperTypeOf($secondExprType)->maybe()) {
					// MOD(x, 0) returns NULL
					$type = TypeCombinator::addNull($type);
				}

				return $this->marshalType($type);

			case $function instanceof AST\Functions\SqrtFunction:
				$exprType = $this->unmarshalType($this->walkSimpleArithmeticExpression($function->simpleArithmeticExpression));

				$type = new FloatType();
				if (TypeCombinator::containsNull($exprType)) {
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
				if (TypeCombinator::containsNull($stringType) || TypeCombinator::containsNull($firstExprType) || TypeCombinator::containsNull($secondExprType)) {
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
		$expressionTypes = [];
		$allTypesContainNull = true;

		foreach ($coalesceExpression->scalarExpressions as $expression) {
			if (!$expression instanceof AST\Node) {
				$expressionTypes[] = new MixedType();
				continue;
			}

			$type = $this->unmarshalType($expression->dispatch($this));
			$allTypesContainNull = $allTypesContainNull && TypeCombinator::containsNull($type);

			$expressionTypes[] = $type;
		}

		$type = TypeCombinator::union(...$expressionTypes);

		if (!$allTypesContainNull) {
			$type = TypeCombinator::removeNull($type);
		}

		return $this->marshalType($type);
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
				$thenScalarExpression->dispatch($this)
			);
		}

		if ($elseScalarExpression instanceof AST\Node) {
			$types[] = $this->unmarshalType(
				$elseScalarExpression->dispatch($this)
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
				$thenScalarExpression->dispatch($this)
			);
		}

		if ($elseScalarExpression instanceof AST\Node) {
			$types[] = $this->unmarshalType(
				$elseScalarExpression->dispatch($this)
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

			if (class_exists(TypedExpression::class) && $expr instanceof TypedExpression) {
				$enforcedType = $this->resolveDoctrineType(Types::INTEGER);
				$type = TypeTraverser::map($type, static function (Type $type, callable $traverse) use ($enforcedType): Type {
					if ($type instanceof UnionType || $type instanceof IntersectionType) {
						return $traverse($type);
					}
					if ($type instanceof NullType) {
						return $type;
					}
					if ($enforcedType->accepts($type, true)->yes()) {
						return $type;
					}
					if ($enforcedType instanceof StringType) {
						if ($type instanceof IntegerType || $type instanceof FloatType) {
							return TypeCombinator::union($type->toString(), $type);
						}
						if ($type instanceof BooleanType) {
							return TypeCombinator::union($type->toInteger()->toString(), $type);
						}
					}
					return $enforcedType;
				});
			} else {
				// Expressions default to Doctrine's StringType, whose
				// convertToPHPValue() is a no-op. So the actual type depends on
				// the driver and PHP version.
				// Here we assume that the value may or may not be casted to
				// string by the driver.
				$type = TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
					if ($type instanceof UnionType || $type instanceof IntersectionType) {
						return $traverse($type);
					}
					if ($type instanceof IntegerType || $type instanceof FloatType) {
						return TypeCombinator::union($type->toString(), $type);
					}
					if ($type instanceof BooleanType) {
						return TypeCombinator::union($type->toInteger()->toString(), $type);
					}
					return $traverse($type);
				});
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
		switch ($aggExpression->functionName) {
			case 'MAX':
			case 'MIN':
				$type = $this->unmarshalType(
					$this->walkSimpleArithmeticExpression($aggExpression->pathExpression)
				);

				return $this->marshalType(TypeCombinator::addNull($type));

			case 'AVG':
				$type = $this->unmarshalType(
					$this->walkSimpleArithmeticExpression($aggExpression->pathExpression)
				);

				$type = TypeCombinator::union($type, $type->toFloat());
				$type = TypeUtils::generalizeType($type, GeneralizePrecision::lessSpecific());

				return $this->marshalType(TypeCombinator::addNull($type));

			case 'SUM':
				$type = $this->unmarshalType(
					$this->walkSimpleArithmeticExpression($aggExpression->pathExpression)
				);

				$type = TypeUtils::generalizeType($type, GeneralizePrecision::lessSpecific());

				return $this->marshalType(TypeCombinator::addNull($type));

			case 'COUNT':
				return $this->marshalType(IntegerRangeType::fromInterval(0, null));

			default:
				return $this->marshalType(new MixedType());
		}
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
				$type = new ConstantStringType($value);
				break;

			case AST\Literal::BOOLEAN:
				$value = strtolower($literal->value) === 'true' ? 1 : 0;
				$type = new ConstantIntegerType($value);
				break;

			case AST\Literal::NUMERIC:
				$value = $literal->value;
				assert(is_numeric($value));

				if (floatval(intval($value)) === floatval($value)) {
					$type = new ConstantIntegerType((int) $value);
				} else {
					$type = new ConstantFloatType((float) $value);
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
			$type = $this->unmarshalType($this->walkArithmeticPrimary($term));
			$types[] = TypeUtils::generalizeType($type, GeneralizePrecision::lessSpecific());
		}

		$type = TypeCombinator::union(...$types);
		$type = $this->toNumericOrNull($type);

		return $this->marshalType($type);
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

		foreach ($term->arithmeticFactors as $factor) {
			if (!$factor instanceof AST\Node) {
				// Skip '*' or '/'
				continue;
			}
			$type = $this->unmarshalType($this->walkArithmeticPrimary($factor));
			$types[] = TypeUtils::generalizeType($type, GeneralizePrecision::lessSpecific());
		}

		$type = TypeCombinator::union(...$types);
		$type = $this->toNumericOrNull($type);

		return $this->marshalType($type);
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
		$type = TypeUtils::generalizeType($type, GeneralizePrecision::lessSpecific());

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
		if ($enumType !== null) {
			$type = new ObjectType($enumType);
		} else {
			try {
				$type = $this->descriptorRegistry
					->get($typeName)
					->getWritableToPropertyType();
				if ($type instanceof NeverType) {
					$type = new MixedType();
				}
			} catch (DescriptorNotRegisteredException $e) {
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
			$type = $this->descriptorRegistry
				->get($typeName)
				->getDatabaseInternalType();
		} catch (DescriptorNotRegisteredException $e) {
			$type = new MixedType();
		}

		if ($enumType !== null) {
			$enumTypes = array_map(static function ($enumType) {
				return ConstantTypeHelper::getTypeFromValue($enumType->value);
			}, $enumType::cases());
			$enumType = TypeCombinator::union(...$enumTypes);
			$enumType = TypeCombinator::union($enumType, $enumType->toString());
			$type = TypeCombinator::intersect($enumType, $type);
		}

		if ($nullable) {
			$type = TypeCombinator::addNull($type);
		}

		return $type;
	}

	private function toNumericOrNull(Type $type): Type
	{
		return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type {
			if ($type instanceof UnionType || $type instanceof IntersectionType) {
				return $traverse($type);
			}
			if ($type instanceof NullType || $type instanceof IntegerType) {
				return $type;
			}
			if ($type instanceof BooleanType) {
				return $type->toInteger();
			}
			return TypeCombinator::union(
				$type->toFloat(),
				$type->toInteger()
			);
		});
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

	private function hasAggregateFunction(AST\SelectStatement $AST): bool
	{
		foreach ($AST->selectClause->selectExpressions as $selectExpression) {
			if (!$selectExpression instanceof AST\SelectExpression) {
				continue;
			}

			$expression = $selectExpression->expression;

			switch (true) {
				case $expression instanceof AST\Functions\AvgFunction:
				case $expression instanceof AST\Functions\CountFunction:
				case $expression instanceof AST\Functions\MaxFunction:
				case $expression instanceof AST\Functions\MinFunction:
				case $expression instanceof AST\Functions\SumFunction:
				case $expression instanceof AST\AggregateExpression:
					return true;
				default:
					break;
			}
		}

		return false;
	}

}
