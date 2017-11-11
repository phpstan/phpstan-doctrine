<?php declare(strict_types = 1);

namespace Tests\PHPStan\Reflection\Doctrine;

use PHPStan\Reflection\Doctrine\DoctrineSelectableClassReflectionExtension;

final class DoctrineSelectableClassReflectionExtensionTest extends \PHPStan\Testing\TestCase
{

	/** @var \PHPStan\Broker\Broker */
	private $broker;

	/** @var \PHPStan\Reflection\Doctrine\DoctrineSelectableClassReflectionExtension */
	private $extension;

	protected function setUp()
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
	public function testHasMethod(string $className, string $method, bool $expectedResult)
	{
		$classReflection = $this->broker->getClass($className);
		$this->assertSame($expectedResult, $this->extension->hasMethod($classReflection, $method));
	}

	public function testGetMethod()
	{
		$classReflection = $this->broker->getClass(\Doctrine\Common\Collections\Collection::class);
		$methodReflection = $this->extension->getMethod($classReflection, 'matching');
		$this->assertSame('matching', $methodReflection->getName());
	}

}
