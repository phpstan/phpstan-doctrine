<?php declare(strict_types = 1);

namespace PHPStan\DoctrineIntegration\Persistence\ManagerRegistryRepositoryDynamicReturn;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use RuntimeException;

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
        $test = $this->managerRegistry->getRepository(MyEntity::class)->createQueryBuilder('e');

        $test->getQuery();
    }

	public function errorWithDynamicType(): void
	{
		$this->managerRegistry->getRepository(MyEntity::class)->nonexistant();
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
