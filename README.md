# Doctrine extensions for PHPStan

[![Build Status](https://travis-ci.org/phpstan/phpstan-doctrine.svg)](https://travis-ci.org/phpstan/phpstan-doctrine)
[![Latest Stable Version](https://poser.pugx.org/phpstan/phpstan-doctrine/v/stable)](https://packagist.org/packages/phpstan/phpstan-doctrine)
[![License](https://poser.pugx.org/phpstan/phpstan-doctrine/license)](https://packagist.org/packages/phpstan/phpstan-doctrine)

* [PHPStan](https://github.com/phpstan/phpstan)
* [Doctrine](http://www.doctrine-project.org/)

This extension provides following features:

* Provides correct return type for `Doctrine\ORM\EntityManager::find`, `getReference` and `getPartialReference` when `Foo::class` entity class name is provided as the first argument
* Adds missing `matching` method on `Doctrine\Common\Collections\Collection`

## Usage

To use this extension, require it in [Composer](https://getcomposer.org/):

```
composer require --dev phpstan/phpstan-doctrine
```

And include extension.neon in your project's PHPStan config:

```
includes:
	- vendor/phpstan/phpstan-doctrine/extension.neon
```
