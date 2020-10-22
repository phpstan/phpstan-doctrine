<?php declare(strict_types = 1);

namespace PHPStan\Rules\Doctrine\ORM;

interface MyExtendedInterface extends MyInterface
{
    public function getName(): string;
}
