<?php declare(strict_types = 1);

namespace Tests\PHPStan\Type\Doctrine;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Doctrine\DoctrineSelectableDynamicReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPUnit\Framework\TestCase;

final class DoctrineSelectableDynamicReturnTypeExtensionTest extends TestCase
{

	/** @var \PHPStan\Type\Doctrine\DoctrineSelectableDynamicReturnTypeExtension */
	private $extension;

	protected function setUp(): void
	{
		$this->extension = new DoctrineSelectableDynamicReturnTypeExtension();
	}

	/**
	 * @return mixed[]
	 */
	public function dataIsMethodSupported(): array
	{
		return [
			['matching', true],
			['filter', false],
			['foo', false],
		];
	}

	/**
	 * @dataProvider dataIsMethodSupported
	 * @param string $method
	 * @param bool $expectedResult
	 */
	public function testIsMethodSupported(string $method, bool $expectedResult): void
	{
		$methodReflection = $this->createMock(MethodReflection::class);
		$methodReflection->method('getName')->willReturn($method);
		self::assertSame($expectedResult, $this->extension->isMethodSupported($methodReflection));
	}

	public function testGetTypeFromMethodCall(): void
	{
		$methodReflection = $this->createMock(MethodReflection::class);

		$scope = $this->createMock(Scope::class);
		$scope->method('getType')->will(
			self::returnCallback(
				function (): Type {
					return new ObjectType(\Doctrine\Common\Collections\Collection::class);
				}
			)
		);

		$var = $this->createMock(Expr::class);
		$methodCall = $this->createMock(MethodCall::class);
		$methodCall->var = $var;

		$resultType = $this->extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

		self::assertInstanceOf(ObjectType::class, $resultType);
		self::assertSame(\Doctrine\Common\Collections\Collection::class, $resultType->describe());
	}

}
