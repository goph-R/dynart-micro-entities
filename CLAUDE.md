# CLAUDE.md

## Project Overview

**dynart-micro-entities** is a PDO-based ORM/entity library for the [dynart-micro](../dynart-micro) framework. It provides database abstraction, PHP 8 attribute-driven entity metadata, query building, and CRUD with dirty-field tracking. Namespace `Dynart\Micro\Entities`, PSR-4 from `src/`.

Depends on `dynart/micro` via Composer path repository (symlinked from `../dynart-micro`). PHP 8.0+.

The test suite lives in a **separate repository** at `../dynart-micro-entities-test/`.

## Running Tests

```bash
# from ../dynart-micro-entities-test/
php vendor/bin/phpunit --testsuite unit --stderr
php vendor/bin/phpunit --testsuite integration --stderr   # requires MariaDB
php vendor/bin/phpunit --stderr
```

## Architecture

### Database Layer

`Database` (abstract) → `MariaDatabase` (MySQL/MariaDB). Wraps PDO with lazy connection, prepared statements, logging, and transaction support. `PdoBuilder` constructs PDO instances via fluent API.

Key feature: `#ClassName` tokens in SQL are replaced with `<prefix>classname` (outside string literals). Example: `#User` → `app_user`.

Config keys use the pattern `database.{configName}.{key}` (default config name is `"default"`).

> **INI gotcha:** DSN values containing `=` must be quoted in the INI file:
> `database.default.dsn = "mysql:host=localhost"` — bare `=` breaks `parse_ini_file` with `INI_SCANNER_TYPED`.

### Entity System

`Entity` (abstract) — base for all persistent objects. Tracks new/persisted state (`isNew`/`setNew`). Dirty-tracking via snapshot (`takeSnapshot`, `getDirtyFields`, `isDirty`, `clearSnapshot`, `snapshot`). Provides lifecycle event name helpers (`beforeSaveEvent`, `afterSaveEvent`, `beforeDeleteEvent`, `afterDeleteEvent`).

**Event naming** follows the dynart-micro convention `namespace:snake_case`. Entity events are `entity.<alias-or-FQCN>:<event>`, e.g. `entity.content:before_save`. The `entity.` prefix is permanent — it keeps ORM-level events from colliding with an application's service-level events (a `ContentService` emitting `content:before_delete`). The namespace defaults to the fully-qualified class name with `\` replaced by `.`, which is always unique; declare `protected static string $eventName = 'content';` in a subclass for a readable short alias.

`#[Column]` (PHP 8 attribute on Entity properties) — declares column metadata. All column-related constants live here: `TYPE_*`, `ACTION_*`, `NOW`.

`ColumnAttributeHandler` — implements `AttributeHandlerInterface`; reads `#[Column]` attributes via reflection and calls `EntityManager::addColumn()`. Registered via dynart-micro's `AttributeProcessor` middleware.

`#[Table]` (PHP 8 attribute on Entity classes) — table-level metadata: an override for the table `name`, plus composite `unique` constraints and multi-column `index`es that cannot be expressed per-property. Single-column constraints belong on `#[Column(unique: true)]` / `#[Column(index: true)]`.

`TableAttributeHandler` — the `TARGET_CLASS` counterpart of `ColumnAttributeHandler`; register both with the `AttributeProcessor` middleware.

`EntityManager` — central registry (`className → [columnName → Column]`). Handles `save` (insert/update with dirty tracking), `findById`, `findByIds`, `deleteById`, `deleteByIds`, `insert`, `update`, `fetchDataArray`, `setByDataArray`, plus `table`, `uniqueConstraints` and `indexes` for the schema metadata. Emits before/after save **and delete** events via `EventServiceInterface`.

**`deleteById()` / `deleteByIds()` load before deleting.** The delete events have to carry the row's previous state (an auditing listener has no other way to get it), so both methods select the affected entities first and emit per entity. `findById()` returns `null` for a missing row rather than fataling on a `false` from PDO.

### Query System

`Query` — fluent domain object representing a SELECT (fields, joins, conditions, group by, order by, limit/offset). Supports subqueries as the `from` source.

`QueryBuilder` (abstract) → `MariaQueryBuilder` — converts Query + EntityManager metadata into SQL strings. Also generates `CREATE TABLE` DDL from entity metadata.

`QueryExecutor` — executes queries: `isTableExist`, `createTable`, `listTables`, `findColumns`, `findAll`, `findAllColumn`, `findAllCount`.

### Key Patterns

- **Abstract + Concrete**: `Database`/`MariaDatabase`, `QueryBuilder`/`MariaQueryBuilder` — designed for multiple DB backends
- **Query Object Pattern**: `Query` is a data object; `QueryBuilder` generates SQL; `QueryExecutor` runs it
- **Metadata Registry**: `EntityManager` stores `Column` objects keyed by class and property name
- **Lazy Connection**: `Database` connects on first `query()` call
- **Dirty Tracking**: only changed fields are sent in UPDATE; snapshot taken after every save/load

## Column Attribute

```php
#[Column(
    type: Column::TYPE_STRING,   // required
    size: 100,                   // int or [precision, scale] for numeric
    fixSize: false,              // char vs varchar
    notNull: false,
    autoIncrement: false,
    primaryKey: false,
    default: null,               // Column::NOW for utc timestamps; ['raw()'] for raw SQL
    foreignKey: null,            // [TargetClass::class, 'column']
    onDelete: null,              // Column::ACTION_CASCADE or Column::ACTION_SET_NULL
    onUpdate: null,
    unique: false,               // single column unique constraint
    index: false,                // single column index
)]
```

## Table Attribute

```php
#[Table(
    name: null,                                    // override the derived table name
    unique: ['uq_pair' => ['content_id', 'tag_id']],  // keys are optional constraint names
    index: [['type', 'status', 'published_at']],
)]
```

## Configuration

```ini
database.default.dsn           = "mysql:host=localhost"
database.default.name          = mydb
database.default.username      = root
database.default.password      = secret
database.default.table_prefix  = app_

entities.query_builder.max_limit = 1000
```

## Known Gotchas

- **`Database::update()` param name collision** — condition params must not share a placeholder name with any column being updated. Use distinct names (e.g. `:oldName`) in the condition.
- **`orderBy()` is whitelisted** — the order field name is checked against the query's selectable fields before it reaches the SQL. Accepted are aliases of aliased fields, plain names of non-aliased ones, and (when the query selects no explicit fields) every column of the source table. Raw expression fields are never sortable by name. An unmatched order field is silently dropped.
