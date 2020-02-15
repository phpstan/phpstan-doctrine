<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\ORM\Mapping\MappingException;
use PHPUnit\Framework\TestCase;

final class ObjectMetadataResolverTest extends TestCase
{

	/** @var ObjectMetadataResolver */
	private $resolver;

	protected function setUp(): void
	{
		$this->resolver = new ObjectMetadataResolver(__DIR__ . '/entity-manager.php', null);
	}

	public function testGetRepositoryClassThrowOnInvalidEntity(): void
	{
		$this->expectException(MappingException::class);
		$this->expectExceptionMessage(
			sprintf('Class "%s" is not a valid entity or mapped super class.', MyInvalidEntity::class)
		);

		$this->resolver->getRepositoryClass(MyInvalidEntity::class);
	}

	public function testGetRepositoryClassIgnoreErrorWithAbstractClass(): void
	{
		$repositoryClass = $this->resolver->getRepositoryClass(MyInvalidAbstractEntity::class);

		self::assertEquals('Doctrine\ORM\EntityRepository', $repositoryClass);
	}

}
