<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\DBAL;

use Doctrine\DBAL\Connection;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ConstantScalarType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\VerbosityLevel;
use function array_map;
use function count;
use function in_array;
use function is_int;

/**
 * @implements Rule<Node\Expr\MethodCall>
 */
class ArrayParameterTypeRule implements Rule
{

	private const CONNECTION_QUERY_METHODS_LOWER = [
		'fetchassociative',
		'fetchnumeric',
		'fetchone',
		'delete',
		'insert',
		'fetchallnumeric',
		'fetchallassociative',
		'fetchallkeyvalue',
		'fetchallassociativeindexed',
		'fetchfirstcolumn',
		'iteratenumeric',
		'iterateassociative',
		'iteratekeyvalue',
		'iterateassociativeindexed',
		'iteratecolumn',
		'executequery',
		'executecachequery',
		'executestatement',
	];

	public function getNodeType(): string
	{
		return Node\Expr\MethodCall::class;
	}

	public function processNode(Node $node, Scope $scope): array
	{
		if (! $node->name instanceof Node\Identifier) {
			return [];
		}

		if (count($node->getArgs()) < 2) {
			return [];
		}

		$calledOnType = $scope->getType($node->var);

		$connection = 'Doctrine\DBAL\Connection';
		if (! (new ObjectType($connection))->isSuperTypeOf($calledOnType)->yes()) {
			return [];
		}

		$methodName = $node->name->toLowerString();
		if (! in_array($methodName, self::CONNECTION_QUERY_METHODS_LOWER, true)) {
			return [];
		}

		$params = $scope->getType($node->getArgs()[1]->value);

		$typesArray = $node->getArgs()[2] ?? null;
		$typesArrayType = $typesArray !== null
			? $scope->getType($typesArray->value)
			: null;

		foreach ($params->getConstantArrays() as $arrayType) {
			$values = $arrayType->getValueTypes();
			$keys = [];
			foreach ($values as $i => $value) {
				if (!$value->isArray()->yes()) {
					continue;
				}

				$keys[] = $arrayType->getKeyTypes()[$i];
			}

			if ($keys === []) {
				continue;
			}

			$typeConstantArrays = $typesArrayType !== null
				? $typesArrayType->getConstantArrays()
				: [];

			if ($typeConstantArrays === []) {
				return array_map(
					static function (ConstantScalarType $type) {
						return RuleErrorBuilder::message(
							'Parameter at '
							. $type->describe(VerbosityLevel::precise())
							. ' is an array, but is not hinted as such to doctrine.'
						)
							->identifier('doctrine.parameterType')
							->build();
					},
					$keys
				);
			}

			foreach ($typeConstantArrays as $typeConstantArray) {
				$issueKeys = [];
				foreach ($keys as $key) {
					$valueType = $typeConstantArray->getOffsetValueType($key);

					$values = $valueType->getConstantScalarValues();
					if ($values === []) {
						$issueKeys[] = $key;
					}

					foreach ($values as $scalarValue) {
						if (is_int($scalarValue) && !(($scalarValue & Connection::ARRAY_PARAM_OFFSET) !== Connection::ARRAY_PARAM_OFFSET)) {
							continue;
						}

						$issueKeys[] = $key;
					}

					return array_map(
						static function (ConstantScalarType $type) {
							return RuleErrorBuilder::message(
								'Parameter at '
								. $type->describe(VerbosityLevel::precise())
								. ' is an array, but is not hinted as such to doctrine.'
							)->identifier('doctrine.parameterType')
								->build();
						},
						$issueKeys
					);
				}
			}
		}

		return [];
	}

}
