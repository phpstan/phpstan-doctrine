<?php declare(strict_types = 1);

namespace Tests\PHPStan\Type\Doctrine;

use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Doctrine\EntityManagerFindDynamicReturnTypeExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ObjectWithoutClassType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use PHPUnit\Framework\TestCase;

final class EntityManagerFindDynamicReturnTypeExtensionTest extends TestCase
{

	const ENTITY_CLASS_NAME = '\\Foo\\Bar';

	/** @var \PHPStan\Type\Doctrine\EntityManagerFindDynamicReturnTypeExtension */
	private $extension;

	protected function setUp()
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
	public function testIsMethodSupported(string $method, bool $expectedResult)
	{
		$methodReflection = $this->createMock(MethodReflection::class);
		$methodReflection->method('getName')->willReturn($method);
		$this->assertSame($expectedResult, $this->extension->isMethodSupported($methodReflection));
	}

	/**
	 * @return mixed[]
	 */
	public function dataGetTypeFromMethodCallWithClassConstFetch(): array
	{
		return [
			[
				self::ENTITY_CLASS_NAME,
				'find',
				new UnionType([new ObjectType(self::ENTITY_CLASS_NAME), new NullType()]),
			],
			[
				'self',
				'find',
				new UnionType([new ObjectType(self::ENTITY_CLASS_NAME), new NullType()]),
			],
			[
				'static',
				'find',
				new ObjectWithoutClassType(),
			],
			[
				self::ENTITY_CLASS_NAME,
				'getReference',
				new ObjectType(self::ENTITY_CLASS_NAME),
			],
			[
				'self',
				'getReference',
				new ObjectType(self::ENTITY_CLASS_NAME),
			],
			[
				'static',
				'getReference',
				new ObjectWithoutClassType(),
			],
			[
				self::ENTITY_CLASS_NAME,
				'getPartialReference',
				new ObjectType(self::ENTITY_CLASS_NAME),
			],
			[
				'self',
				'getPartialReference',
				new ObjectType(self::ENTITY_CLASS_NAME),
			],
			[
				'static',
				'getPartialReference',
				new ObjectWithoutClassType(),
			],
		];
	}

	/**
	 * @dataProvider dataGetTypeFromMethodCallWithClassConstFetch
	 * @param string $entityClassName
	 * @param string $method
	 * @param \PHPStan\Type\Type $expectedResult
	 */
	public function testGetTypeFromMethodCallWithClassConstFetch(
		string $entityClassName,
		string $method,
		Type $expectedResult
	)
	{
		$methodReflection = $this->mockMethodReflection($method);
		$scope = $this->mockScope();
		$methodCall = $this->mockMethodCallWithClassConstFetch($entityClassName);

		$resultType = $this->extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

		$this->assertInstanceOf(get_class($expectedResult), $resultType);
		$this->assertSame($expectedResult->describe(), $resultType->describe());
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
				new ObjectWithoutClassType(),
			],
			[
				'self',
				'find',
				new ObjectWithoutClassType(),
			],
			[
				'static',
				'find',
				new ObjectWithoutClassType(),
			],
			[
				self::ENTITY_CLASS_NAME,
				'getReference',
				new ObjectWithoutClassType(),
			],
			[
				'self',
				'getReference',
				new ObjectWithoutClassType(),
			],
			[
				'static',
				'getReference',
				new ObjectWithoutClassType(),
			],
			[
				self::ENTITY_CLASS_NAME,
				'getPartialReference',
				new ObjectWithoutClassType(),
			],
			[
				'self',
				'getPartialReference',
				new ObjectWithoutClassType(),
			],
			[
				'static',
				'getPartialReference',
				new ObjectWithoutClassType(),
			],
		];
	}

	/**
	 * @dataProvider dataGetTypeFromMethodCallWithScalarString
	 * @param string $entityClassName
	 * @param string $method
	 * @param \PHPStan\Type\Type $expectedResult
	 */
	public function testGetTypeFromMethodCallWithScalarString(
		string $entityClassName,
		string $method,
		Type $expectedResult
	)
	{
		$methodReflection = $this->mockMethodReflection($method);
		$scope = $this->mockScope();
		$methodCall = $this->mockMethodCallWithScalarString($entityClassName);

		$resultType = $this->extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

		$this->assertInstanceOf(get_class($expectedResult), $resultType);
		$this->assertSame($expectedResult->describe(), $resultType->describe());
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
