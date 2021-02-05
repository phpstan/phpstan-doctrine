# Doctrine extensions for PHPStan

[![Build](https://github.com/phpstan/phpstan-doctrine/workflows/Build/badge.svg)](https://github.com/phpstan/phpstan-doctrine/actions)
[![Latest Stable Version](https://poser.pugx.org/phpstan/phpstan-doctrine/v/stable)](https://packagist.org/packages/phpstan/phpstan-doctrine)
[![License](https://poser.pugx.org/phpstan/phpstan-doctrine/license)](https://packagist.org/packages/phpstan/phpstan-doctrine)

* [PHPStan](https://phpstan.org/)
* [Doctrine](https://www.doctrine-project.org/)

This extension provides following features:

* DQL validation for parse errors, unknown entity classes and unknown persistent fields. QueryBuilder validation is also supported.
* Recognizes magic `findBy*`, `findOneBy*` and `countBy*` methods on EntityRepository.
* Validates entity fields in repository `findBy`, `findBy*`, `findOneBy`, `findOneBy*`, `count` and `countBy*` method calls.
* Interprets `EntityRepository<MyEntity>` correctly in phpDocs for further type inference of methods called on the repository.
* Provides correct return for `Doctrine\ORM\EntityManager::getRepository()`.
* Provides correct return type for `Doctrine\ORM\EntityManager::find`, `getReference` and `getPartialReference` when `Foo::class` entity class name is provided as the first argument
* Adds missing `matching` method on `Doctrine\Common\Collections\Collection`. This can be turned off by setting `parameters.doctrine.allCollectionsSelectable` to `false`.
* Also supports Doctrine ODM.
* Analysis of discrepancies between entity column types and property field types.

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```bash
composer require --dev phpstan/phpstan-doctrine
```

If you also install [phpstan/extension-installer](https://github.com/phpstan/extension-installer) then you're all set!

<details>
  <summary>Manual installation</summary>

If you don't want to use `phpstan/extension-installer`, include extension.neon in your project's PHPStan config:

```neon
includes:
    - vendor/phpstan/phpstan-doctrine/extension.neon
```

If you're interested in DQL/QueryBuilder validation, include also `rules.neon` (you will also need to provide the `objectManagerLoader`, see below):

```neon
includes:
    - vendor/phpstan/phpstan-doctrine/rules.neon
```
</details>


## Configuration

If your repositories have a common base class, you can configure it in your `phpstan.neon` and PHPStan will see additional methods you define in it:

```neon
parameters:
	doctrine:
		repositoryClass: MyApp\Doctrine\BetterEntityRepository
```

You can opt in for more advanced analysis by providing the object manager from your own application. This will allow the correct entity `repositoryClass` to be inferred when accessing `$entityManager->getRepository()`. Also, it allows DQL validation when enabled:

```neon
parameters:
	doctrine:
		objectManagerLoader: tests/object-manager.php
```

For example, in a Symfony project, `object-manager.php` would look something like this:

```php
<?php

require __DIR__.'/../config/bootstrap.php';
$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
return $kernel->getContainer()->get('doctrine')->getManager();
```

In Symfony 5, the bootstrap file might be located in `tests/` folder or even missing. Check the [Symfony documentation about custom bootstrap process](https://symfony.com/doc/current/testing/bootstrap.html) for more informations.

## Custom types

If your application uses custom Doctrine types, you can write your own type descriptors to analyse them properly.
Type descriptors implement the interface `PHPStan\Type\Doctrine\Descriptors\DoctrineTypeDescriptor` which looks like this:

```php
<?php

public function getType(): string;

public function getWritableToPropertyType(): Type;

public function getWritableToDatabaseType(): Type;
```

* The `getType()` method simply returns the class name of the custom type.
* The `getWritableToPropertyType()` method returns the PHPStan type that the custom type will write into the entity's property field. Basically it is the return type of the custom type's `convertToPHPValue()` method.
* The `getWritableToDatabaseType()` method returns the PHPStan type that can be written from the entity's property field into the custom type. Again, basically it's the allowed type for the custom type's `convertToDatabaseValue()`'s first argument.

Generally, at least for most of Doctrine's native types, these last two methods will return the same type, but it is not always the case. One example would be the `datetime` type, which allows you to set any `\DateTimeInterface` into to property field, but will always contain the `\DateTime` type when loaded from the database.

### Nullable types

Type descriptors don't have to deal with nullable types, as these are transparently added/removed from the descriptor's types as needed. Therefore you don't have to return the union type of your custom type and `NullType` from the descriptor's methods, even if your custom type allows `null`.

### ReflectionDescriptor

If your custom type's `convertToPHPValue()` and `convertToDatabaseValue()` methods have proper typehints, you don't have to write your own descriptor for it. The `PHPStan\Type\Doctrine\Descriptors\ReflectionDescriptor` can analyse the typehints and do the rest for you.

### Registering type descriptors

When you write a custom type descriptor, you have to let PHPStan know about it. Add something like this into your `phpstan.neon`:

```neon
services:
	-
		class: MyCustomTypeDescriptor
		tags: [phpstan.doctrine.typeDescriptor]

	# in case you are using the ReflectionDescriptor
	-
		factory: PHPStan\Type\Doctrine\Descriptors\ReflectionDescriptor('MyApp\MyCustomTypeName')
		tags: [phpstan.doctrine.typeDescriptor]
```
