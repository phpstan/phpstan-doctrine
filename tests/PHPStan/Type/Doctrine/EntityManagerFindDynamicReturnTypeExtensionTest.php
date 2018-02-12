<?php declare(strict_types = 1);

namespace Tests\PHPStan\Type\Doctrine;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Doctrine\EntityManagerFindDynamicReturnTypeExtension;
use PHPStan\Type\ObjectWithoutClassType;
use PHPUnit\Framework\TestCase;

final class EntityManagerFindDynamicReturnTypeExtensionTest extends TestCase
{

	const ENTITY_CLASS_NAME = '\\Foo\\Bar';

	/** @var \PHPStan\Type\Doctrine\EntityManagerFindDynamicReturnTypeExtension */
	private $extension;

	protected function setUp(): void
	{
		$this->extension = new EntityManagerFindDynamicReturnTypeExtension();
	}

	/**
	 * @return mixed[]
	 */
	public function dataIsMethodSupported(): array
	{
		return [
			['find', true],
			['getReference', true],
			['getPartialReference', true],
			['copy', false],
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

	/**
	 * @return mixed[]
	 */
	public function dataGetTypeFromMethodCallWithClassConstFetch(): array
	{
		return [
			[
				'\Foo\Bar',
				'find',
				'\Foo\Bar|null',
			],
			[
				'self',
				'find',
				'\Foo\Bar|null',
			],
			[
				'static',
				'find',
				'mixed',
			],
			[
				'\Foo\Bar',
				'getReference',
				'\Foo\Bar',
			],
			[
				'self',
				'getReference',
				'\Foo\Bar',
			],
			[
				'static',
				'getReference',
				'mixed',
			],
			[
				'\Foo\Bar',
				'getPartialReference',
				'\Foo\Bar',
			],
			[
				'self',
				'getPartialReference',
				'\Foo\Bar',
			],
			[
				'static',
				'getPartialReference',
				'mixed',
			],
		];
	}

	/**
	 * @dataProvider dataGetTypeFromMethodCallWithClassConstFetch
	 * @param string $entityClassName
	 * @param string $method
	 * @param string $expectedTypeDescription
	 */
	public function testGetTypeFromMethodCallWithClassConstFetch(
		string $entityClassName,
		string $method,
		string $expectedTypeDescription
	): void
	{
		$methodReflection = $this->mockMethodReflection($method);
		$scope = $this->mockScope();
		$methodCall = $this->mockMethodCallWithClassConstFetch($entityClassName);

		$resultType = $this->extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

		self::assertSame($expectedTypeDescription, $resultType->describe());
	}

	/**
	 * @return mixed[]
	 */
	public function dataGetTypeFromMethodCallWithScalarString(): array
	{
		return [
			[
				self::ENTITY_CLASS_NAME,
				'find',
				'mixed',
			],
			[
				'self',
				'find',
				'mixed',
			],
			[
				'static',
				'find',
				'mixed',
			],
			[
				self::ENTITY_CLASS_NAME,
				'getReference',
				'mixed',
			],
			[
				'self',
				'getReference',
				'mixed',
			],
			[
				'static',
				'getReference',
				'mixed',
			],
			[
				self::ENTITY_CLASS_NAME,
				'getPartialReference',
				'mixed',
			],
			[
				'self',
				'getPartialReference',
				'mixed',
			],
			[
				'static',
				'getPartialReference',
				'mixed',
			],
		];
	}

	/**
	 * @dataProvider dataGetTypeFromMethodCallWithScalarString
	 * @param string $entityClassName
	 * @param string $method
	 * @param string $expectedTypeDescription
	 */
	public function testGetTypeFromMethodCallWithScalarString(
		string $entityClassName,
		string $method,
		string $expectedTypeDescription
	)
	{
		$methodReflection = $this->mockMethodReflection($method);
		$scope = $this->mockScope();
		$methodCall = $this->mockMethodCallWithScalarString($entityClassName);

		$resultType = $this->extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

		self::assertSame($expectedTypeDescription, $resultType->describe());
	}

	private function mockMethodReflection(string $method): MethodReflection
	{
		$methodReflection = $this->createMock(MethodReflection::class);
		$methodReflection->method('getReturnType')->willReturn(new ObjectWithoutClassType());
		$methodReflection->method('getName')->willReturn($method);
		return $methodReflection;
	}

	private function mockScope(): Scope
	{
		$scope = $this->createMock(Scope::class);
		$scope->method('getClassReflection')->willReturnCallback(
			function (): ClassReflection {
				$classReflection = $this->createMock(ClassReflection::class);
				$classReflection->method('getName')->willReturn(self::ENTITY_CLASS_NAME);
				return $classReflection;
			}
		);
		return $scope;
	}

	private function mockMethodCallWithClassConstFetch(string $entityClassName): \PhpParser\Node\Expr\MethodCall
	{
		$class = new \PhpParser\Node\Name($entityClassName);
		$value = new \PhpParser\Node\Expr\ClassConstFetch($class, 'class');
		$arg = new \PhpParser\Node\Arg($value);

		$methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
		$methodCall->args = [
			0 => $arg,
		];
		return $methodCall;
	}

	private function mockMethodCallWithScalarString(string $entityClassName): \PhpParser\Node\Expr\MethodCall
	{
		$value = new \PhpParser\Node\Scalar\String_($entityClassName);
		$arg = new \PhpParser\Node\Arg($value);

		$methodCall = $this->createMock(\PhpParser\Node\Expr\MethodCall::class);
		$methodCall->args = [
			0 => $arg,
		];
		return $methodCall;
	}

}
