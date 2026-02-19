# CLAUDE.md

This file provides guidance for AI agents working on the phpstan/phpstan-doctrine repository.

## Project Overview

phpstan-doctrine is a PHPStan extension that provides static analysis capabilities for Doctrine ORM, DBAL, and ODM. It adds type inference, DQL validation, QueryBuilder analysis, and custom rules that detect Doctrine-specific bugs at analysis time without running the application.

Key features:
- DQL query validation (parse errors, unknown entities, unknown fields)
- QueryBuilder type inference and validation
- Magic repository method recognition (`findBy*`, `findOneBy*`, `countBy*`)
- Entity column/relation type checking against property types
- Return type inference for `EntityManager::getRepository()`, `find()`, `getReference()`
- Query result type inference for `getResult()`, `getSingleResult()`, etc.
- Doctrine ODM support
- Database driver-aware type resolution for expressions like `SUM()`, `AVG()`

## Repository Structure

```
src/                    # Extension source code (PSR-4: PHPStan\)
├── Classes/            # Forbidden class name extensions (proxy detection)
├── Doctrine/           # Core Doctrine integration (driver detection, metadata loading)
├── PhpDoc/             # PHPDoc type node resolver extensions
├── Reflection/         # Class reflection extensions (repository methods, selectable)
├── Rules/              # Custom PHPStan rules (entity validation, DQL checks)
│   ├── Doctrine/ORM/   # ORM-specific rules
│   └── Gedmo/          # Gedmo doctrine-extensions support
├── Stubs/              # Stub file loader
└── Type/               # Type inference extensions
    └── Doctrine/
        ├── Collection/ # Collection type narrowing
        ├── DBAL/       # DBAL QueryBuilder and Result types
        ├── Descriptors/# Doctrine type → PHPStan type mappings (28 descriptors)
        ├── Query/      # DQL Query result type walker and inference
        └── QueryBuilder/ # ORM QueryBuilder type tracking
tests/                  # Test suite
├── DoctrineIntegration/# Integration tests (ORM, ODM, Persistence)
├── Platform/           # Database platform tests (MySQL, PostgreSQL, SQLite)
├── Reflection/         # Reflection extension tests
├── Rules/              # Rule tests (entity validation, dead code, properties)
└── Type/               # Type inference tests
stubs/                  # PHPStan stub files for Doctrine classes
├── Collections/        # Collection and Selectable stubs
├── DBAL/               # DBAL types, cache, exception stubs
├── ORM/                # ORM Query, QueryBuilder, Mapping stubs
├── Persistence/        # Persistence layer stubs
└── runtime/Enum/       # PHP 8.1 enum polyfill stubs
compatibility/          # Compatibility layer for multiple Doctrine versions
├── patches/            # Composer patches for ORM v3 attribute support
├── AnnotationDriver.php # ORM v2 fallback
├── ArrayType.php       # DBAL v3 fallback
└── orm-3-baseline.php  # Dynamic baseline selection by ORM version
```

## PHP Version Support

This repository supports **PHP 7.4+**. All source code in `src/` and `tests/` must be compatible with PHP 7.4. Do not use language features from PHP 8.0+ (named arguments, union types in signatures, match expressions, etc.) in the main source code.

Some test data files under `tests/*/data-php-*` and `tests/*/data-attributes` target specific PHP versions and are excluded from lint/analysis on older versions.

## Multi-Version Doctrine Support

The extension supports multiple major versions of Doctrine libraries simultaneously:
- **Doctrine ORM**: 2.x and 3.x
- **Doctrine DBAL**: 3.x and 4.x
- **Doctrine ODM**: 2.4+
- **Doctrine Persistence**: 2.x and 3.x

This is achieved through:
- The `compatibility/` directory providing fallback classes for missing APIs
- Composer patches in `compatibility/patches/` for ORM v3 attribute support
- Version-specific PHPStan baselines (`phpstan-baseline-orm-2.neon`, `phpstan-baseline-orm-3.neon`, `phpstan-baseline-dbal-3.neon`, `phpstan-baseline-dbal-4.neon`)
- Dynamic baseline selection in `compatibility/orm-3-baseline.php`
- `method_exists()` checks in source code for version-dependent API calls
- CI matrix testing across version combinations

## Configuration Files

- **`extension.neon`** — Main extension services: type descriptors, dynamic return type extensions, reflection extensions, PHPDoc resolvers. Loaded automatically via phpstan/extension-installer.
- **`rules.neon`** — Custom validation rules (DQL, entity columns, relations, mapping). Optionally included by users who provide an `objectManagerLoader`.
- **`phpstan.neon`** — Self-analysis configuration (level 8, includes all baselines and strict rules).
- **`phpunit.xml`** — Test configuration. The `platform` test group is excluded by default and runs separately in CI.

## Development Commands

All commands are defined in the `Makefile`:

```bash
make check          # Run all checks (lint, cs, tests, phpstan)
make tests          # Run PHPUnit tests
make lint           # Run parallel-lint on src/ and tests/
make cs             # Run coding standard checks (requires make cs-install first)
make cs-fix         # Auto-fix coding standard violations
make cs-install     # Clone phpstan/build-cs repository
make phpstan        # Run PHPStan self-analysis
make phpstan-generate-baseline  # Regenerate the PHPStan baseline
```

## Running Tests

```bash
composer install
make tests          # Runs: php vendor/bin/phpunit
```

Platform tests (requiring database containers) are in the `platform` group and excluded by default. They run in CI via `.github/workflows/platform-test.yml` with MySQL and PostgreSQL services.

## Coding Standards

The project uses the [phpstan/build-cs](https://github.com/phpstan/build-cs) coding standard (branch `2.x`):

```bash
make cs-install     # Clone the build-cs repo
make cs             # Check coding standards
make cs-fix         # Auto-fix violations
```

Key style rules:
- Tab indentation for PHP, XML, and NEON files
- Space indentation (2 spaces) for YAML files
- LF line endings, UTF-8 encoding
- Trailing whitespace trimmed, final newline required

## CI Pipeline

The CI runs on every pull request and push to `2.0.x`:

1. **Lint** — `parallel-lint` on PHP 7.4–8.4
2. **Coding Standard** — phpcs with build-cs rules
3. **Tests** — PHPUnit on PHP 7.4–8.4, both lowest and highest dependencies, plus a special matrix entry with ORM 3.x + DBAL 4.x
4. **Static Analysis** — PHPStan at level 8 on PHP 7.4–8.4, plus ORM 3.x + DBAL 4.x variant
5. **Mutation Testing** — Infection on PHP 8.2–8.4, requires 100% MSI on changed lines
6. **Platform Tests** — PHPUnit `platform` group against real MySQL and PostgreSQL databases

## Architecture Notes

### Type Descriptors

Type descriptors (`src/Type/Doctrine/Descriptors/`) map Doctrine DBAL column types to PHPStan types. Each descriptor implements `DoctrineTypeDescriptor` with:
- `getType()` — Returns the Doctrine type class name
- `getWritableToPropertyType()` — The PHP type Doctrine writes to entity properties
- `getWritableToDatabaseType()` — The PHP type that can be written to the database

Descriptors are registered as services tagged with `phpstan.doctrine.typeDescriptor` in `extension.neon`.

### Query Result Type Walker

`QueryResultTypeWalker` (`src/Type/Doctrine/Query/`) walks the DQL AST to infer result types. It handles SELECT expressions, JOINs, aggregations, NEW expressions, and INDEX BY. It is driver-aware — result types for expressions like `SUM()` or `AVG()` depend on the database driver (MySQL vs PostgreSQL vs SQLite).

### QueryBuilder Type Tracking

The extension tracks QueryBuilder method calls through `QueryBuilderType`, carrying DQL parts as PHPStan type metadata. When `getQuery()` is called, it reconstructs the DQL and runs it through the type walker.

### Custom Rules

Rules in `src/Rules/Doctrine/ORM/` validate entity mappings at analysis time:
- `EntityColumnRule` — Column types match property types
- `EntityRelationRule` — Relation configurations are valid
- `EntityNotFinalRule` — Entities aren't `final` (breaks proxy generation)
- `DqlRule` / `QueryBuilderDqlRule` — DQL syntax and semantic validation
- `EntityMappingExceptionRule` — Catches mapping configuration errors

### Stubs

Stub files in `stubs/` provide PHPStan with type information for Doctrine classes, adding generics (`@template T of object`) and narrowed return types that the original Doctrine source doesn't express. These are loaded via `StubFilesExtensionLoader`.

## Branching

- **`2.0.x`** — Development branch (default for PRs)
- **`main`** — Main/release branch
