# Mago Doctrine extension

`gimmickmedia/mago-doctrine` adds Doctrine ORM (and ODM) support to the [Mago](https://mago.carthage.software) analyzer. It is modelled on [phpstan-doctrine](https://github.com/phpstan/phpstan-doctrine) and works from static metadata: mapping attributes are read from Mago's codebase, DQL is analysed without a database or a booted kernel. An optional `objectManagerLoader` adds validation through the real Doctrine DQL parser.

## Features

| phpstan-doctrine feature | Mago extension |
| --- | --- |
| DQL validation (parse errors, unknown entity classes, unknown persistent fields), also for QueryBuilder | `dql-validation` on `createQuery()` / `QueryBuilder::getQuery()`; with `objectManagerLoader` the DQL is additionally parsed by Doctrine |
| Magic `findBy*`, `findOneBy*`, `countBy*` | return types and argument signatures for mapped fields (ORM, ODM and custom base repositories) |
| Entity field validation in `findBy`, `findBy*`, `findOneBy`, `findOneBy*`, `count`, `countBy*` | `repository-unknown-field` |
| `EntityRepository<MyEntity>` in phpDocs | generic receiver parameters are used to find the entity |
| `EntityManager::getRepository()` | custom repository class or `EntityRepository<Entity>` (`DocumentRepository<Document>` for ODM) |
| `EntityManager::find`, `getReference`, `getPartialReference` with `Foo::class` | `Foo\|null`, `Foo`, `Foo` |
| Missing `Collection::matching()` (`allCollectionsSelectable`) | `patches/collections` adds `matching()`; an issue filter is the fallback when the patch is not configured |
| Doctrine ODM | `#[ODM\Document]`, `Field`, `Id`, `ReferenceOne/Many`, `EmbedOne/Many`, `DocumentRepository` |
| Column type vs. property type (`allowNullablePropertyForRequiredField`) | `entity-column-type` |
| Relation type vs. property type (to-one, to-many) | `entity-relation-type` |
| Return types of `Query::getResult`, `getOneOrNullResult`, `getSingleResult`, `toIterable`, `execute` in `HYDRATE_OBJECT` mode | see *Query type inference* |
| `final` entity classes (allowed with native lazy objects) | `entity-not-final` (`reportFinalEntities`, `nativeLazyObjects`) |
| `final` entity constructors | `entity-constructor-not-final` |
| Mapping configuration errors | `entity-mapping` (missing repository / target entity classes) |
| Direct use of proxy class names | `proxy-class-name` |
| Throw types of `EntityManager::flush()` | `ORMException`, `UniqueConstraintViolationException` |
| Dead code integration (generated ids, versions, read-only entities, Gedmo) | mapped properties count as read; ids, generated values, versions, read-only entities and Gedmo-managed properties count as written/initialised |
| `Collection::first()` / `last()` narrowing after `isEmpty()` is `false` | `T\|false` becomes `T` (needs the `stubs` include) |
| Custom types (`reportUnknownTypes`) | `types` mirrors Doctrine's `dbal.types` class registrations; `customTypes` can directly map type names to PHP types; unknown types use `entity-unknown-column-type` |
| Custom DQL functions | `customFunctions` option (name => return type) |
| `reportDynamicQueryBuilders` | `dynamic-query-builder` |
| `literalString` | `literal-string` for DQL/SQL arguments of `createQuery()` and `Doctrine\DBAL\Connection` query methods |

Issue codes are prefixed with `gimmickmedia/doctrine/`.

## Query type inference

```php
$query = $entityManager->createQuery('SELECT u FROM Acme\User u');
$query->getResult();                              // list<Acme\User>
$query->getResult(Query::HYDRATE_OBJECT);         // list<Acme\User>
$query->getOneOrNullResult();                     // mixed (hydration mode unknown)
$query->getOneOrNullResult(Query::HYDRATE_OBJECT); // Acme\User|null
$query->getSingleResult(Query::HYDRATE_OBJECT);   // Acme\User
$query->toIterable([], Query::HYDRATE_OBJECT);    // Traversable<int, Acme\User>
$query->execute(null, Query::HYDRATE_OBJECT);     // list<Acme\User>

$entityManager->createQuery('SELECT u.id, u.email, COALESCE(u.name, \'Anonymous\') AS name FROM Acme\User u')
    ->getSingleResult(Query::HYDRATE_OBJECT);    // array{id: int, email: string, name: string}

$entityManager->createQueryBuilder()->select('u')->from(User::class, 'u')->getQuery()->getResult(); // list<Acme\User>
```

`createQuery()` and `QueryBuilder::getQuery()` return `Doctrine\ORM\Query<TKey, TResult>`. Supported: entity and joined aliases, scalar fields, mixed entity/scalar results, `DISTINCT`, all `JOIN` flavours (association and `WITH` joins), `INDEX BY`, `NEW`, `IDENTITY()`, `COALESCE`, `NULLIF`, `CASE`, aggregates, arithmetic and string functions and configured custom functions. Sub-queries are inferred as `mixed`. Aggregate/arithmetic results are driver dependent and inferred conservatively (e.g. `AVG()` is `float|string|null`).

QueryBuilder calls are tracked whether chained or not (`$qb->from(...)` on a variable). Keep `select`/`from`/`join` arguments literal and do not pass the builder to other methods; dynamic `where`/`orderBy`/`groupBy` expressions are fine. Enable `reportDynamicQueryBuilders` to see where inference is not possible.

## Use

Require `gimmickmedia/mago-doctrine` and `carthage-software/mago`, then register a worker in the consuming project (`.mago/extensions.php`):

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

(new Mago\Sdk\Worker(
    Gimmickmedia\Mago\Doctrine\DoctrineExtension::create(
        entityNamespaces: ['App\\Entity'],
        reportFinalEntities: true,
        nativeLazyObjects: false,
        allowNullablePropertyForRequiredField: false,
        ormRepositoryClass: 'Doctrine\\ORM\\EntityRepository',
        odmRepositoryClass: 'Doctrine\\ODM\\MongoDB\\Repository\\DocumentRepository',
        types: [
            'datetime_immutable' => 'Carbon\\Doctrine\\DateTimeImmutableType',
            'datetime' => 'Carbon\\Doctrine\\DateTimeType',
        ],
        customTypes: ['uuid' => Mago\Sdk\Analyzer\Type::namedObject('Symfony\\Component\\Uid\\Uuid')],
        customFunctions: ['FLOOR' => Mago\Sdk\Analyzer\Type::int()],
        reportUnknownTypes: false,
        reportDynamicQueryBuilders: false,
        allCollectionsSelectable: true,
        objectManagerLoader: null, // e.g. 'tests/object-manager.php', returning an EntityManager or ManagerRegistry
        literalString: false,
    ),
))->run();
```

and configure `mago.toml`:

```toml
[source]
includes = ["vendor", "vendor/gimmickmedia/mago-doctrine/stubs"]
# only when allCollectionsSelectable is enabled
patches = ["vendor/gimmickmedia/mago-doctrine/patches/collections"]

[extension-hosts.doctrine]
command = ["php", ".mago/extensions.php"]
```

`entityNamespaces` accepts namespace prefixes; an empty list searches all classes for Doctrine mapping attributes. `types` follows Doctrine's `dbal.types` mapping and resolves a custom type from its `convertToPHPValue()` return type; built-in descriptors cover Symfony UID, Ramsey UUID and Carbon date types. `customTypes` remains available for direct DBAL-name-to-PHP-type mappings. The object manager loader follows the phpstan-doctrine convention (see its README for Symfony examples); it is loaded once per worker and only used for DQL validation.

## Development

From `tools/mago`, run `composer test` (PHPUnit). The real Mago corpus is under `mago-doctrine/tests/corpus`; its expected diagnostics are locked in a strict baseline and checked with `vendor/bin/mago --workspace mago-doctrine/tests/corpus analyze --verify-baseline`.

Issues from the before-analysis mapping checks are project-level and are not matched by inline `@mago-expect` pragmas, so the corpus uses a baseline for them.
