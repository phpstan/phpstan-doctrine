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
* Analysis of discrepancies between entity column types and property field types. This can be relaxed with the `allowNullablePropertyForRequiredField: true` setting.
* Provides return type for `Doctrine\ORM\Query::getResult`, `getOneOrNullResult`, `getSingleResult`, `toIterable` and `execute` in `HYDRATE_OBJECT` mode (see below).

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
		ormRepositoryClass: MyApp\Doctrine\BetterEntityRepository
		odmRepositoryClass: MyApp\Doctrine\BetterDocumentRepository
```

You can opt in for more advanced analysis by providing the object manager from your own application. This will enable DQL validation:

```neon
parameters:
	doctrine:
		objectManagerLoader: tests/object-manager.php
```

Example for Symfony 4:

```php
// tests/object-manager.php

use App\Kernel;

require __DIR__ . '/../config/bootstrap.php';
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
return $kernel->getContainer()->get('doctrine')->getManager();
```

Example for Symfony 5:

```php
// tests/object-manager.php

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
return $kernel->getContainer()->get('doctrine')->getManager();
```

## Query type inference

This extension can infer the result type of DQL queries when an `objectManagerLoader` is provided.

Examples:

```php
$query = $entityManager->createQuery('SELECT u FROM Acme\User u');
$query->getResult(); // array<Acme\User>

$query = $entityManager->createQuery('SELECT u.id, u.email, u.name FROM Acme\User u');
$query->getResult(); // array<array{id: int, email: string, name: string|null}>

$query = $entityManager->createQuery('
    SELECT u.id, u.email, COALESCE(u.name, "Anonymous") AS name
    FROM   Acme\User u
');
$query->getSingleResult(Query::HYDRATE_OBJECT); // array{id: int, email: string, name: string}>

$query = $entityManager->createQueryBuilder()
    ->select('u')
    ->from(User::class, 'u')
    ->getQuery();
$query->getResult(); // array<Acme\User>
```

Queries are analyzed statically and do not require a running database server. This makes use of the Doctrine DQL parser and entities metadata.

Most DQL features are supported, including `GROUP BY`, `DISTINCT`, all flavors of `JOIN`, arithmetic expressions, functions, aggregations, `NEW`, etc. Sub queries and `INDEX BY` are not yet supported (infered type will be `mixed`).

### Query type inference of expressions

Whether e.g. `SUM(e.column)` is fetched as `float`, `numeric-string` or `int` highly [depends on drivers, their setup and PHP version](https://github.com/janedbal/php-database-drivers-fetch-test).
This extension autodetects your setup and provides quite accurate results for `pdo_mysql`, `mysqli`, `pdo_sqlite`, `sqlite3`, `pdo_pgsql` and `pgsql`.
Sadly, this autodetection often needs real database connection, so in order to utilize precise types, your `objectManagerLoader` need to be able to connect to real database.

If you are using `bleedingEdge`, the connection failure is propagated. If not, it will be silently ignored and the type will be `mixed` or an union of possible types.

### Supported methods

The `getResult` method is supported when called without argument, or with the hydrateMode argument set to `Query::HYDRATE_OBJECT`:

``` php
$query = $entityManager->createQuery('SELECT u FROM Acme\User u');

$query->getResult(); // array<User>

$query->getResult(Query::HYDRATE_OBJECT); // array<User>
```

The methods `getOneOrNullResult`, `getSingleResult`, `toIterable`, and `execute` are supported when the hydrateMode argument is explicitly set to `Query::HYDRATE_OBJECT`:

``` php
$query = $entityManager->createQuery('SELECT u FROM Acme\User u');

$query->getOneOrNullResult(); // mixed

$query->getOneOrNullResult(Query::HYDRATE_OBJECT); // User
```

This is due to the design of the `Query` class preventing from determining the hydration mode used by these functions unless it is specified explicitly during the call.

### Problematic approaches

Not every QueryBuilder can be statically analysed, here are few advices to maximize type inferring:
- Do not pass QueryBuilder to methods
- Do not use dynamic expressions in QueryBuilder methods (mainly in `select`/`join`/`from`/`set`)

You can enable reporting of places where inferring is unavailable by:

```neon
parameters:
	doctrine:
		reportDynamicQueryBuilders: true
```

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

If parent of your type is one of the Doctrine's non-abstract ones, `ReflectionDescriptor` will reuse its descriptor even for expression resolution (e.g. `AVG(t.cost)`).
For example, if you extend `Doctrine\DBAL\Types\DecimalType`, it will know that sqlite fetches that as `float|int` and other drivers as `numeric-string`.
If you extend only `Doctrine\DBAL\Types\Type`, you should use custom descriptor and optionally implement even `DoctrineTypeDriverAwareDescriptor` to provide driver-specific resolution.

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

### Ensure types have descriptor

If you want to be sure you never forget descriptor for some custom type, you can enable:

```neon
parameters:
	doctrine:
		reportUnknownTypes: true
```

This causes failures when your entity uses custom type without descriptor:

```php
#[Entity]
abstract class Uuid7Entity
{

    #[Id]
    #[Column(type: Uuid7Type::NAME)] // reported when descriptor for such type is missing
    private Uuid7 $hsCode;

```
