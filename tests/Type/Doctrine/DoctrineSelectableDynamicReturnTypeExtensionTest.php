<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use Doctrine\Common\Collections\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;
use PHPUnit\Framework\TestCase;

final class DoctrineSelectableDynamicReturnTypeExtensionTest extends TestCase
{

	/** @var DoctrineSelectableDynamicReturnTypeExtension */
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
				static function (): Type {
					return new ObjectType(Collection::class);
				}
			)
		);

		$var = $this->createMock(Expr::class);
		$methodCall = $this->createMock(MethodCall::class);
		$methodCall->var = $var;

		$resultType = $this->extension->getTypeFromMethodCall($methodReflection, $methodCall, $scope);

		self::assertInstanceOf(ObjectType::class, $resultType);
		self::assertSame(Collection::class, $resultType->describe(VerbosityLevel::value()));
	}

}
