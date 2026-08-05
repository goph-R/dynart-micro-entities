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

Key feature: `#ClassName` tokens in SQL are replaced with the registered table name (outside string literals). Example: `#User` → `app_user`. `EntityManager` registers itself as the resolver, so a `#[Table(name: 'user_role')]` override is honoured — without that the substitution would compute `app_userrole` and silently query a table that does not exist. A token that matches no entity falls back to `<prefix>classname`.

Config keys use the pattern `database.{configName}.{key}` (default config name is `"default"`).

> **INI gotcha:** DSN values containing `=` must be quoted in the INI file:
> `database.default.dsn = "mysql:host=localhost"` — bare `=` breaks `parse_ini_file` with `INI_SCANNER_TYPED`.

**Booleans are bound as 0 and 1**, in `query()`, before `execute()` sees them. PDO binds every parameter as a string and `false` as a string is `''`, which a server in strict mode refuses for an integer column — so a `bool` field wrote fine on a lenient machine and failed on a correctly configured one. Anything else added to that path should be normalised in the same place rather than at the call sites, which is where the casts used to live and get forgotten.

**Test against a strict server.** `STRICT_TRANS_TABLES` is the default in MySQL since 5.7 and MariaDB since 10.2, but XAMPP ships without it, so this whole class of bug is invisible locally. `DatabaseTest` sets it for its own connection.

### Entity System

`Entity` (abstract) — base for all persistent objects. Tracks new/persisted state (`isNew`/`setNew`). Dirty-tracking via snapshot (`takeSnapshot`, `getDirtyFields`, `isDirty`, `clearSnapshot`, `snapshot`). Provides lifecycle event name helpers (`beforeSaveEvent`, `afterSaveEvent`, `beforeDeleteEvent`, `afterDeleteEvent`).

**Event naming** follows the dynart-micro convention `namespace:snake_case`. Entity events are `entity.<alias-or-FQCN>:<event>`, e.g. `entity.content:before_save`. The `entity.` prefix is permanent — it keeps ORM-level events from colliding with an application's service-level events (a `ContentService` emitting `content:before_delete`). The namespace defaults to the fully-qualified class name with `\` replaced by `.`, which is always unique; declare `protected static string $eventName = 'content';` in a subclass for a readable short alias.

`#[Column]` (PHP 8 attribute on Entity properties) — declares column metadata. All column-related constants live here: `TYPE_*`, `ACTION_*`, `NOW`.

`ColumnAttributeHandler` — implements `AttributeHandlerInterface`; reads `#[Column]` attributes via reflection and calls `EntityManager::addColumn()`. Registered via dynart-micro's `AttributeProcessor` middleware.

`#[Table]` (PHP 8 attribute on Entity classes) — table-level metadata: the table `name` (**declare it, it is never derived from CamelCase** — a guess eventually disagrees with what was wanted, silently), plus composite `unique` constraints and multi-column `index`es that cannot be expressed per-property. Single-column constraints belong on `#[Column(unique: true)]` / `#[Column(index: true)]`.

`#[Auditable]` (PHP 8 attribute on Entity classes) — marks an entity for history. See *Auditing* below.

`TableAttributeHandler` / `AuditableAttributeHandler` — the `TARGET_CLASS` counterparts of `ColumnAttributeHandler`; register all three with the `AttributeProcessor` middleware.

`EntityManager::registerEntity()` — reflection-based registration of one entity (`#[Table]`, `#[Auditable]`, then `#[Column]`), for library-provided entities like `Revision` that the application's namespace scan does not cover, and for tests.

`EntityManager` — central registry (`className → [columnName → Column]`). Handles `save` (insert/update with dirty tracking), `findById`, `findByIds`, `deleteById`, `deleteByIds`, `insert`, `update`, `fetchDataArray`, `setByDataArray`, plus `table`, `uniqueConstraints` and `indexes` for the schema metadata. Emits before/after save **and delete** events via `EventServiceInterface`.

**`deleteById()` / `deleteByIds()` load before deleting.** The delete events have to carry the row's previous state (an auditing listener has no other way to get it), so both methods select the affected entities first and emit per entity. `findById()` returns `null` for a missing row rather than fataling on a `false` from PDO.

### Auditing

`#[Auditable]` on an entity gives it a mirror table named `<table>_aud`. `AuditService` subscribes to that entity's after-save and after-delete events and copies the full row in, tagged with a revision id and `rev_type` (`add` / `mod` / `del`).

The mirror is **derived** from the source `#[Column]` metadata, differing in four deliberate ways:

- the primary key is widened with `rev_id` — the same row appears once per revision
- unique constraints are dropped — same reason
- foreign keys are dropped — history must survive the deletion of what it references
- auto-increment is dropped — values are copied from the source row

**One row per entity per revision.** All changes in a request share one revision, created lazily on the first write. Changing the same entity twice inside one revision *overwrites* its earlier row rather than adding a second (`AuditService` deletes then inserts, keeping it portable) — the row holds the entity's state at the end of the revision. Call `reset()` to start a new revision when the individual steps matter.

**Revisions are kept forever.** `Revision.created_at` and `user_id` are both indexed, since the table only grows and history is queried by time.

`EntityManager::save()` emits its after-save event with the operation (`OPERATION_INSERT` / `OPERATION_UPDATE` / `OPERATION_NONE`) as a **second argument** — a listener cannot work it out afterwards, because once the row is written the entity is neither new nor dirty either way, and a save with no dirty field writes nothing at all.

Wiring: `AuditService::subscribeAll()` must run *after* the attribute processor. `postConstruct()` hooks it to `app:init_finished`, so an application only has to register and instantiate the service.

### Migrations

`MigrationInterface` — `version()` (unique, sortable) and `up()`. Migrations are resolved through the DI container, so they can inject `QueryExecutor` and friends.

`Migrations` — register classes with `add()`, then `run()` applies every pending one in ascending version order and records it in the `MigrationHistory` table. The registry is open so plugins can add their own; versions interleave by sort order.

**Adding a column to a live table is `QueryExecutor::addColumnWithAudit()`**, never hand-written `alter table` in a migration — the definition then comes from the same `#[Column]` metadata as the `CREATE TABLE`, so a live table and a fresh install cannot drift. It does the `_aud` mirror as well, and that is not optional: an audited write copies the whole row, so a mirror one column short fails the next save of every entity of that class.

There is **no `down()`** — a mistake is corrected by a new migration. Each migration is recorded as soon as it succeeds, so a failure part-way leaves the earlier ones applied and the run can be repeated. Wrapping the run in a transaction would not help: DDL commits implicitly in MariaDB.

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

- **`orderBy()` is whitelisted** — the order field name is checked against the query's selectable fields before it reaches the SQL. Accepted are aliases of aliased fields, plain names of non-aliased ones, and (when the query selects no explicit fields) every column of the source table. Raw expression fields are never sortable by name. An unmatched order field is silently dropped.
