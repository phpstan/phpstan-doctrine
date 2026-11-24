<?php

namespace PHPStan\Rules\Doctrine\ORM\EntityRepositorySelectable;

use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;

class MyEntity
{

}

/**
 * @extends ObjectRepository<MyEntity>
 * @extends Selectable<int, MyEntity>
 */
interface MyRepositoryInterface extends ObjectRepository, Selectable
{

}

/**
 * @extends EntityRepository<MyEntity>
 */
class MyRepository extends EntityRepository implements MyRepositoryInterface
{

}