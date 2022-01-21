<?php declare(strict_types = 1);

namespace PHPStan\Reflection\Doctrine;

use Doctrine\Common\Collections\Collection;
use PHPStan\Broker\Broker;
use PHPStan\Testing\PHPStanTestCase;

final class DoctrineSelectableClassReflectionExtensionTest extends PHPStanTestCase
{

	/** @var Broker */
	private $broker;

	/** @var DoctrineSelectableClassReflectionExtension */
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
			[Collection::class, 'matching', true],
			[Collection::class, 'foo', false],
		];
	}

	/**
	 * @dataProvider dataHasMethod
	 */
	public function testHasMethod(string $className, string $method, bool $expectedResult): void
	{
		$classReflection = $this->broker->getClass($className);
		self::assertSame($expectedResult, $this->extension->hasMethod($classReflection, $method));
	}

	public function testGetMethod(): void
	{
		$classReflection = $this->broker->getClass(Collection::class);
		$methodReflection = $this->extension->getMethod($classReflection, 'matching');
		self::assertSame('matching', $methodReflection->getName());
	}

}
