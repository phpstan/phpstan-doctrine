<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\Persistence\ManagerRegistryRepositoryDynamicReturn;

use Doctrine\Persistence\ManagerRegistry;
use function PHPStan\Testing\assertType;

class Example
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    public function findDynamicType(): void
    {
        $repo = $this->managerRegistry->getRepository(MyEntity::class);
		assertType('Doctrine\ORM\EntityRepository<PHPStan\DoctrineIntegration\Persistence\ManagerRegistryRepositoryDynamicReturn\MyEntity>', $repo);
		assertType('Doctrine\ORM\QueryBuilder', $repo->createQueryBuilder('e'));
    }
}

/**
 * @ORM\Entity()
 */
class MyEntity
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    private $id;
}
