<?php declare(strict_types = 1);

namespace PHPStan\Type\Doctrine;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\AnnotatedEmbeddable;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\AnnotatedEntity;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\AnnotatedMappedSuperclass;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\AttributeEmbeddable;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\AttributeEntity;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\AttributeMappedSuperclass;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\NotMapped;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\NotMappedUnknownToReflection;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\NotMappedWithDocComment;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\NotMappedWithObjectManagerLoader;
use PHPStan\Type\Doctrine\ObjectMetadataResolverData\NotMappedWithoutReflectionProvider;
use function class_exists;

/**
 * @runTestsInSeparateProcesses
 */
class ObjectMetadataResolverTest extends PHPStanTestCase
{

	private const TMP_DIR = __DIR__ . '/../../../tmp';

	/**
	 * @dataProvider notMappedClassProvider
	 * @param class-string $className
	 */
	public function testIsTransientDoesNotLoadNotMappedClassWithoutObjectManagerLoader(string $className): void
	{
		$resolver = new ObjectMetadataResolver(null, self::TMP_DIR, self::createReflectionProvider());

		self::assertTrue($resolver->isTransient($className));
		self::assertFalse(class_exists($className, false));
	}

	/**
	 * @return iterable<string, array{class-string}>
	 */
	public static function notMappedClassProvider(): iterable
	{
		yield 'without doc comment' => [NotMapped::class];
		yield 'with unrelated doc comment' => [NotMappedWithDocComment::class];
	}

	/**
	 * @dataProvider mappedClassProvider
	 * @param class-string $className
	 */
	public function testIsTransientLoadsMappedClassWithoutObjectManagerLoader(string $className): void
	{
		$resolver = new ObjectMetadataResolver(null, self::TMP_DIR, self::createReflectionProvider());

		$resolver->isTransient($className);
		self::assertTrue(class_exists($className, false));
	}

	/**
	 * @return iterable<string, array{class-string}>
	 */
	public static function mappedClassProvider(): iterable
	{
		yield 'entity attribute' => [AttributeEntity::class];
		yield 'mapped superclass attribute' => [AttributeMappedSuperclass::class];
		yield 'embeddable attribute' => [AttributeEmbeddable::class];
		yield 'entity annotation' => [AnnotatedEntity::class];
		yield 'fully qualified mapped superclass annotation' => [AnnotatedMappedSuperclass::class];
		yield 'embeddable annotation' => [AnnotatedEmbeddable::class];
	}

	public function testIsTransientLoadsClassWithoutReflectionProvider(): void
	{
		$resolver = new ObjectMetadataResolver(null, self::TMP_DIR);

		self::assertTrue($resolver->isTransient(NotMappedWithoutReflectionProvider::class));
		self::assertTrue(class_exists(NotMappedWithoutReflectionProvider::class, false));
	}

	public function testIsTransientLoadsClassUnknownToReflection(): void
	{
		$reflectionProvider = $this->createMock(ReflectionProvider::class);
		$reflectionProvider->method('hasClass')->willReturn(false);
		$resolver = new ObjectMetadataResolver(null, self::TMP_DIR, $reflectionProvider);

		self::assertTrue($resolver->isTransient(NotMappedUnknownToReflection::class));
		self::assertTrue(class_exists(NotMappedUnknownToReflection::class, false));
	}

	public function testIsTransientLoadsClassWithObjectManagerLoader(): void
	{
		$resolver = new ObjectMetadataResolver(
			__DIR__ . '/../../Rules/Doctrine/ORM/entity-manager.php',
			self::TMP_DIR,
			self::createReflectionProvider(),
		);

		self::assertTrue($resolver->isTransient(NotMappedWithObjectManagerLoader::class));
		self::assertTrue(class_exists(NotMappedWithObjectManagerLoader::class, false));
	}

}
