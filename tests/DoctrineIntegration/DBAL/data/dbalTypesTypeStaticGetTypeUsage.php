<?php
declare(strict_types=1);

namespace PHPStan\DoctrineIntegration\DBAL\DBALTypesTypeStaticGetTypeUsage;

use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;

final class Example
{
    public function typeWithClassConstant(): JsonType
    {
        return Type::getType(JsonType::class);
    }

    public function typeWithString(): JsonType
    {
        return Type::getType('Doctrine\DBAL\Types\JsonType');
    }
}
