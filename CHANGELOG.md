# Changelog

All notable changes to **dynart-micro-entities** are documented in this file.
Format follows [Keep a Changelog](https://keepachangelog.com/).

---

## [0.3.1] &ndash; 2026-08-04

### Fixed
- **`Migrations::add()` did not register the migration in the DI container**, but `run()` resolves migrations through it — so every real caller had to remember a second `Micro::add()` or the run died with "was not added". `add()` now registers the class itself, the same thing `AbstractApp::addMiddleware()` does for middlewares. The 0.3.0 tests hid this by registering the classes by hand; they no longer do.

---

## [0.3.0] &ndash; 2026-08-04

### Added
- **Auditing** — `#[Auditable]` on an entity gives it an `<table>_aud` mirror; `AuditService` subscribes to its after-save and after-delete events and copies the full row in, tagged with a revision id and `rev_type` (`add` / `mod` / `del`). Supporting pieces: `AuditableAttributeHandler`, the `Revision` entity, `QueryBuilder::createAuditTable()`, `QueryExecutor::createAuditTable()` / `createTableWithAudit()` / `isAuditTableExist()` / `dropAuditTable()`.
  - The mirror is derived from the source `#[Column]` metadata, differing in four ways: the primary key is widened with `rev_id`, unique constraints are dropped (the same row appears once per revision), foreign keys are dropped (history must survive the deletion of what it references), and auto-increment is dropped.
  - An entity has **one row per revision**, holding its state at the end of it. All changes in a request share a revision, created lazily on the first write; `reset()` starts a new one.
  - Revisions are kept forever, so `Revision.created_at` and `user_id` are indexed.
  - `setEnabled(false)` suspends writing for bulk imports and fixtures.
- **Migrations** — `MigrationInterface` (`version()` + `up()`) and the `Migrations` runner, which applies pending migrations in ascending version order and records each in the `MigrationHistory` table. Migrations resolve through the DI container. The registry is open so plugins can add their own.
- `EntityManager::registerEntity()` — reflection-based registration of a single entity, for library-provided ones like `Revision` and for tests
- `EntityManager::setAuditable()`, `isAuditable()`, `auditableClasses()`, `auditTableName()`, `safeAuditTableName()`, `primaryKeyColumns()`
- `QueryExecutor::dropTable()` / `dropAuditTable()`, `QueryBuilder::dropTableByClass()` / `dropAuditTableByClass()`

### Changed
- **`EntityManager::save()` emits its after-save event with a second argument**, the operation that actually happened (`OPERATION_INSERT` / `OPERATION_UPDATE` / `OPERATION_NONE`). A listener cannot work it out afterwards: once the row is written the entity is neither new nor dirty either way, and a save with no dirty field writes nothing at all. Existing single-argument listeners are unaffected.
- `QueryBuilder::primaryKeyDefinition(string $className)` now delegates to the new abstract `primaryKeyColumnsDefinition(array $columnNames)`, so the audit mirror can declare a primary key that no entity describes. Subclasses must implement `primaryKeyColumnsDefinition()` and `dropTable()` instead of `primaryKeyDefinition()`.

### Fixed
- `Revision::$id` needed a default to survive `fetchDataArray()`; typed properties without one throw when read before initialization

---

## [0.2.0] &ndash; 2026-08-04

### Added
- **Delete events** — `Entity::EVENT_BEFORE_DELETE` / `EVENT_AFTER_DELETE` with the matching `beforeDeleteEvent()` / `afterDeleteEvent()` helpers. `EntityManager::deleteById()` and `deleteByIds()` previously emitted nothing at all, so a delete was invisible to listeners.
- `EntityManager::findByIds()` — fetches multiple entities by primary key, marked as persisted with a snapshot taken
- **`#[Table]` attribute** — table-level metadata: `name` override, composite `unique` constraints and multi-column `index`es, none of which can be expressed per-property. Handled by the new `TableAttributeHandler` (`TARGET_CLASS`).
- **`unique` and `index` on `#[Column]`** — single-column constraints, emitted into the generated `CREATE TABLE`
- `EntityManager::table()`, `uniqueConstraints()` and `indexes()` — the merged column-level and table-level constraint metadata
- `QueryBuilder::uniqueDefinition()` and `indexDefinition()` abstract methods, implemented by `MariaQueryBuilder`; `createTable()` emits both
- `Entity::snapshot()` — reads back the stored snapshot
- `Entity::eventNamespace()` and `Entity::event()` — build event names for an entity class

### Fixed
- **`orderBy()` ignored non-aliased fields.** `QueryBuilder::orderBy()` matched the order field against the *keys* of `$query->fields()`, which are integers for a plain field list, so those fields could never be sorted. Worse, a query with no explicit fields selects every column yet could not be ordered by any of them. Ordering now accepts aliases, plain non-aliased names, and every column of the source table when no fields are given. Raw expression fields remain unsortable by name.
- **`findById()` fataled on a missing row.** It called `setNew()` on the `false` returned by PDO. It now returns `?Entity`.

### Changed
- **Entity event names follow the dynart-micro `namespace:snake_case` convention.** `beforeSaveEvent()` returned `Fully\Qualified\Class.before_save` — a dot separator and a fully-qualified class name, which matched nothing else in the framework. It is now `entity.<alias-or-FQCN>:before_save`. The `entity.` prefix is permanent so ORM-level events cannot collide with an application's service-level events; the namespace defaults to the FQCN with `\` replaced by `.` and can be shortened with `protected static string $eventName` on a subclass. **Breaking change** for any existing subscriber.
- `deleteById()` and `deleteByIds()` load the affected entities before deleting them, so the events carry the previous state — one extra select per call. `deleteById()` on a missing row is now a no-op instead of an unconditional `DELETE`.
- `deleteByIds()` throws a clear `EntityManagerException` for composite primary keys instead of failing inside `escapeName()`

---

## [0.1.0] &ndash; 2023-07-17

### Added
- Extracted from **dynart-micro 0.7.0**: `Database`, `MariaDatabase`, `PdoBuilder`
- `Entity` base class with new/persisted state and snapshot-based dirty tracking
- `#[Column]` attribute with all column metadata constants, and `ColumnAttributeHandler`
- `EntityManager` metadata registry with `save`, `findById`, `deleteById`, `deleteByIds`
- `Query` / `QueryBuilder` / `MariaQueryBuilder` / `QueryExecutor` query layer
- `CREATE TABLE` generation from entity metadata
- `#ClassName` table name substitution in raw SQL
