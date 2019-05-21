# Doctrine extensions for PHPStan

[![Build Status](https://travis-ci.org/phpstan/phpstan-doctrine.svg)](https://travis-ci.org/phpstan/phpstan-doctrine)
[![Latest Stable Version](https://poser.pugx.org/phpstan/phpstan-doctrine/v/stable)](https://packagist.org/packages/phpstan/phpstan-doctrine)
[![License](https://poser.pugx.org/phpstan/phpstan-doctrine/license)](https://packagist.org/packages/phpstan/phpstan-doctrine)

* [PHPStan](https://github.com/phpstan/phpstan)
* [Doctrine](http://www.doctrine-project.org/)

This extension provides following features:

* DQL validation for parse errors, unknown entity classes and unknown persistent fields. QueryBuilder validation is also supported.
* Recognizes magic `findBy*`, `findOneBy*` and `countBy*` methods on EntityRepository.
* Validates entity fields in repository `findBy`, `findBy*`, `findOneBy`, `findOneBy*`, `count` and `countBy*` method calls.
* Interprets `EntityRepository<MyEntity>` correctly in phpDocs for further type inference of methods called on the repository.
* Provides correct return for `Doctrine\ORM\EntityManager::getRepository()`.
* Provides correct return type for `Doctrine\ORM\EntityManager::find`, `getReference` and `getPartialReference` when `Foo::class` entity class name is provided as the first argument
* Adds missing `matching` method on `Doctrine\Common\Collections\Collection`. This can be turned off by setting `parameters.doctrine.allCollectionsSelectable` to `false`.
* Also supports Doctrine ODM.

## Installation

To use this extension, require it in [Composer](https://getcomposer.org/):

```
composer require --dev phpstan/phpstan-doctrine
```

If you also install [phpstan/extension-installer](https://github.com/phpstan/extension-installer) then you're all set!

<details>
  <summary>Manual installation</summary>

If you don't want to use `phpstan/extension-installer`, include extension.neon in your project's PHPStan config:

```
includes:
    - vendor/phpstan/phpstan-doctrine/extension.neon
```

If you're interested in DQL/QueryBuilder validation, include also `rules.neon` (you will also need to provide the `objectManagerLoader`, see below):

```
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
require dirname(__DIR__).'/../config/bootstrap.php';
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
return $kernel->getContainer()->get('doctrine')->getManager();
```
