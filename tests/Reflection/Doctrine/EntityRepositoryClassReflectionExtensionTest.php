<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

final class EntityRepositoryClassReflectionExtensionTest extends \PHPStan\Testing\TestCase
{

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	/** @var \PHPStan\Reflection\Doctrine\EntityRepositoryClassReflectionExtension */
	private $extension;

	protected function setUp(): void
	{
		$this->broker = $this->createBroker();
		$this->extension = new EntityRepositoryClassReflectionExtension();
	}

	/**
	 * @return mixed[]
	 */
	public function dataHasMethod(): array
	{
		return [
			[\Doctrine\ORM\EntityRepository::class, 'findBy', true],
			[\Doctrine\ORM\EntityRepository::class, 'findByString', true],
			[\Doctrine\ORM\EntityRepository::class, 'findOneByString', true],
			[\Doctrine\ORM\EntityRepository::class, 'count', false],
			[\Doctrine\ORM\EntityRepository::class, 'find', false],
			[\Doctrine\ORM\EntityRepository::class, 'findAll', false],
		];
	}

	/**
	 * @dataProvider dataHasMethod
	 *
	 * @param string $className
	 * @param string $method
	 * @param bool $expectedResult
	 */
	public function testHasMethod(string $className, string $method, bool $expectedResult): void
	{
		$classReflection = $this->broker->getClass($className);

		self::assertSame($expectedResult, $this->extension->hasMethod($classReflection, $method));
	}

	public function testGetMethod(): void
	{
		$methodName = 'findOneByString';

		$classReflection = $this->broker->getClass(\Doctrine\ORM\EntityRepository::class);
		$methodReflection = $this->extension->getMethod($classReflection, $methodName);

		self::assertSame($methodName, $methodReflection->getName());
	}

}
