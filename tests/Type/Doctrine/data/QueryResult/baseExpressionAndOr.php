<?php declare(strict_types = 1);

namespace QueryResult\CreateQuery;

use Doctrine\ORM\Query\Expr\Andx;
use function PHPStan\Testing\assertType;

$and = new Andx();
$count1 = $and->count();
assertType("int<0, max>", $count1);

$modifiedAnd = $and->add('a = b');
assertType("Doctrine\ORM\Query\Expr\Andx", $modifiedAnd);

$string = $and->__toString();
assertType("string", $string);
