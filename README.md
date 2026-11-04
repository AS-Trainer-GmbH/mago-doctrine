# Mago Doctrine extension

Doctrine ORM analyzer support for Mago, backed by static metadata from Doctrine mapping attributes. It does not boot a Symfony kernel or EntityManager.

## Features

- Infers custom or generic `EntityRepository<Entity>` return types from `getRepository()` calls.
- Infers return types and argument signatures for `findBy<Field>()`, `findOneBy<Field>()`, and `countBy<Field>()` repository methods when the field is mapped.
- Treats identifier, version, and supported Gedmo-managed properties as initialized by Doctrine/framework hydration.
- Checks mapped column and to-one relation property types, references mapped fields, and optionally reports final entities.

DQL and `QueryBuilder` result inference and DQL validation are not provided because they require live ORM metadata or application bootstrapping.

## Use

Require `astrainer/mago-doctrine` and `carthage-software/mago`, then register a worker from the consuming project:

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

(new Mago\Sdk\Worker(
    Astrainer\Mago\Doctrine\DoctrineExtension::create(
        entityNamespaces: ['App\\Entity'],
        reportFinalEntities: true,
    ),
))->run();
```

Configure the worker under `[extension-hosts.doctrine]` in `mago.toml`:

```toml
[extension-hosts.doctrine]
command = ["php", ".mago/extensions.php"]
```

`entityNamespaces` accepts namespace prefixes; an empty list searches all classes for Doctrine entity attributes. `reportFinalEntities` defaults to `true`.

## Development

From `tools/mago`, run `composer test`. The real Mago corpus is under `mago-doctrine/tests/corpus`; its expected diagnostics are locked in a strict baseline and checked with `vendor/bin/mago --workspace mago-doctrine/tests/corpus analyze --verify-baseline`.

Issues from the before-analysis mapping checks are project-level and are not matched by inline `@mago-expect` pragmas, so the corpus uses a baseline for them.