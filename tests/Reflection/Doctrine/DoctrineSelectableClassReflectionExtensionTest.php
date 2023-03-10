<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use Doctrine\Common\Collections\Collection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;

final class DoctrineSelectableClassReflectionExtensionTest extends PHPStanTestCase
{

	/** @var ReflectionProvider */
	private $reflectionProvider;

	/** @var DoctrineSelectableClassReflectionExtension */
	private $extension;

	protected function setUp(): void
	{
		$this->reflectionProvider = $this->createReflectionProvider();
		$this->extension = new DoctrineSelectableClassReflectionExtension($this->reflectionProvider);
	}

	/**
	 * @return mixed[]
	 */
	public function dataHasMethod(): array
	{
		return [
			[Collection::class, 'matching', true],
			[Collection::class, 'foo', false],
		];
	}

	/**
	 * @dataProvider dataHasMethod
	 */
	public function testHasMethod(string $className, string $method, bool $expectedResult): void
	{
		$classReflection = $this->reflectionProvider->getClass($className);
		self::assertSame($expectedResult, $this->extension->hasMethod($classReflection, $method));
	}

	public function testGetMethod(): void
	{
		$classReflection = $this->reflectionProvider->getClass(Collection::class);
		$methodReflection = $this->extension->getMethod($classReflection, 'matching');
		self::assertSame('matching', $methodReflection->getName());
	}

}
