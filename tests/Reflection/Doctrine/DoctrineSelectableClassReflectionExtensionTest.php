<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

final class DoctrineSelectableClassReflectionExtensionTest extends \PHPStan\Testing\PHPStanTestCase
{

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	/** @var \PHPStan\Reflection\Doctrine\DoctrineSelectableClassReflectionExtension */
	private $extension;

	protected function setUp(): void
	{
		$this->broker = $this->createBroker();

		$this->extension = new DoctrineSelectableClassReflectionExtension();
		$this->extension->setBroker($this->broker);
	}

	/**
	 * @return mixed[]
	 */
	public function dataHasMethod(): array
	{
		return [
			[\Doctrine\Common\Collections\Collection::class, 'matching', true],
			[\Doctrine\Common\Collections\Collection::class, 'foo', false],
		];
	}

	/**
	 * @dataProvider dataHasMethod
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
		$classReflection = $this->broker->getClass(\Doctrine\Common\Collections\Collection::class);
		$methodReflection = $this->extension->getMethod($classReflection, 'matching');
		self::assertSame('matching', $methodReflection->getName());
	}

}
