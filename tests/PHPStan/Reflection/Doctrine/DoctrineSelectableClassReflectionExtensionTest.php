<?php declare(strict_types = 1);

namespace Tests\PHPStan\Reflection\Doctrine;

use PHPStan\Broker\Broker;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\Doctrine\DoctrineSelectableClassReflectionExtension;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\Php\PhpMethodReflection;
use PHPUnit\Framework\TestCase;

final class DoctrineSelectableClassReflectionExtensionTest extends TestCase
{

	/** @var \PHPStan\Reflection\Doctrine\DoctrineSelectableClassReflectionExtension */
	private $extension;

	protected function setUp()
	{
		$broker = $this->mockBroker();

		$this->extension = new DoctrineSelectableClassReflectionExtension();
		$this->extension->setBroker($broker);
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
		$classReflection = $this->mockClassReflection(new \ReflectionClass($className));
		$this->assertSame($expectedResult, $this->extension->hasMethod($classReflection, $method));
	}

	public function testGetMethod()
	{
		$classReflection = $this->mockClassReflection(new \ReflectionClass(\Doctrine\Common\Collections\Collection::class));
		$methodReflection = $this->extension->getMethod($classReflection, 'matching');
		$this->assertSame('matching', $methodReflection->getName());
	}

	private function mockBroker(): Broker
	{
		$broker = $this->createMock(Broker::class);

		$broker->method('getClass')->willReturnCallback(
			function (string $className): ClassReflection {
				return $this->mockClassReflection(new \ReflectionClass($className));
			}
		);

		return $broker;
	}

	private function mockClassReflection(\ReflectionClass $reflectionClass): ClassReflection
	{
		$classReflection = $this->createMock(ClassReflection::class);
		$classReflection->method('getName')->willReturn($reflectionClass->getName());
		$classReflection->method('getNativeMethod')->willReturnCallback(
			function (string $method) use ($reflectionClass): MethodReflection {
				return $this->mockMethodReflection($reflectionClass->getMethod($method));
			}
		);

		return $classReflection;
	}

	private function mockMethodReflection(\ReflectionMethod $method): PhpMethodReflection
	{
		$methodReflection = $this->createMock(PhpMethodReflection::class);
		$methodReflection->method('getName')->willReturn($method->getName());
		return $methodReflection;
	}

}
