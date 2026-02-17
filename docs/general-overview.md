# dynart-micro-entities — General Overview

**dynart-micro-entities** is a PDO-based ORM/entity library for the [dynart-micro](../dynart-micro) framework. It provides database abstraction, entity metadata management, query building, and CRUD operations. Namespace: `Dynart\Micro\Entities`, PSR-4 autoloaded from `src/`.

## Architecture

The library is organized into four layers that collaborate to map PHP classes to database tables.

```
#[Column] attributes on Entity subclasses
        ↓ (processed by)
ColumnAttributeHandler → EntityManager  (metadata registry)
                               ↓
                         Database (PDO wrapper)
                               ↓
              QueryBuilder → QueryExecutor  (query layer)
```

---

## Layer 1 — Entity

### `Entity` (abstract)

Base class for all persistent objects. Subclasses declare public properties that correspond to database columns.

**Responsibilities:**
- Tracks whether the object is new (never saved) or persisted (`isNew()` / `setNew()`).
- Maintains a **snapshot** of field values at load/save time so only changed fields are sent to the database (dirty tracking).
- Provides lifecycle event name helpers (`beforeSaveEvent()`, `afterSaveEvent()`) that return strings like `MyEntity.before_save` for use with `EventService`.

**Constants:** `EVENT_BEFORE_SAVE`, `EVENT_AFTER_SAVE`

---

## Layer 2 — Database

### `Database` (abstract)

Thin PDO wrapper with lazy connection, prepared statements, logging, and transaction helpers. Subclasses implement `connect()`, `escapeName()`, and `escapeLike()`.

**Key features:**
- **`#ClassName` substitution** — any `#Word` token in a SQL string (outside single quotes) is replaced with the configured table prefix + lowercased word. Example: `select * from #User` → `select * from app_user`.
- `query()` — prepares and executes a parameterized statement; logs queries at DEBUG level.
- `fetch()` / `fetchAll()` — execute and hydrate results as associative arrays or typed objects (`PDO::FETCH_CLASS`).
- `fetchColumn()` / `fetchOne()` — scalar/column helpers.
- `insert()` / `update()` — convenience DML builders that escape names and bind parameters.
- `getInConditionAndParams()` — builds a `IN (...)` clause with named parameters.
- `beginTransaction()` / `commit()` / `rollBack()` / `runInTransaction()` — transaction support (silently handles implicit commits from DDL statements).

Config keys follow the pattern `database.{configName}.{key}` (default config name is `"default"`).

### `MariaDatabase` (MySQL / MariaDB)

Concrete `Database` for MariaDB/MySQL. Connects via a DSN from config, selects the database, sets `utf8` charset. Escapes identifiers with backticks; escapes `LIKE` wildcards by prepending a backslash to `%`.

### `PdoBuilder`

Fluent builder for `PDO` instances. Accepts `dsn`, `username`, `password`, and `options` via chainable setters, then creates the PDO via `build()`. Injected into `Database` to facilitate testing.

---

## Layer 3 — Entity Metadata

### `#[Column]` attribute (`Attribute\Column`)

PHP 8 attribute applied to **Entity** properties to declare column metadata. It is also the single home for all column-related constants.

**Constructor parameters:**

| Parameter | Type | Description |
|---|---|---|
| `type` | string | One of the `TYPE_*` constants |
| `size` | int\|array | Column size; `[precision, scale]` for `numeric` |
| `fixSize` | bool | Use `CHAR` instead of `VARCHAR` for strings |
| `notNull` | bool | Add `NOT NULL` constraint |
| `autoIncrement` | bool | Auto-increment column |
| `primaryKey` | bool | Mark as primary key |
| `default` | mixed | Default value (`Column::NOW` for date/time, `null` for NULL, raw array for unquoted SQL) |
| `foreignKey` | array\|null | `[TargetClass::class, 'column']` |
| `onDelete` | string\|null | `Column::ACTION_CASCADE` or `Column::ACTION_SET_NULL` |
| `onUpdate` | string\|null | `Column::ACTION_CASCADE` or `Column::ACTION_SET_NULL` |

**Constants:**

| Constant | Value | Purpose |
|---|---|---|
| `TYPE_INT` … `TYPE_BLOB` | `'int'` … | Column data types |
| `ACTION_CASCADE` | `'cascade'` | FK referential action |
| `ACTION_SET_NULL` | `'set_null'` | FK referential action |
| `NOW` | `'now'` | Sentinel for UTC current-time column defaults |

### `ColumnAttributeHandler`

Implements `AttributeHandlerInterface` (from dynart-micro). Reads `#[Column]` attributes from entity class properties via reflection and passes the `Column` object directly to `EntityManager::addColumn()`.

Registered in the application alongside the `AttributeProcessor` middleware so entity classes are automatically discovered at startup.

### `EntityManager`

Central metadata registry. Stores a mapping of `className → [columnName → Column]` and `className → tableName`.

**Responsibilities:**
- `addColumn()` — registers a `Column` object for a given class and property name.
- `tableName()` / `tableNameByClass()` / `safeTableName()` — resolve table names (with optional prefix; supports `#ClassName` hash-name mode).
- `primaryKey()` — returns the primary key column name (or array for composite keys), cached on first call.
- `isPrimaryKeyAutoIncrement()` — checks whether the PK column uses auto-increment.
- `primaryKeyCondition()` / `primaryKeyConditionParams()` / `primaryKeyValue()` — build WHERE clause fragments for PK lookups.
- `insert()` / `update()` — thin delegates to `Database`.
- `findById()` — fetches a single entity by PK, hydrates it as the correct class, marks it as not-new, and takes a snapshot.
- `deleteById()` / `deleteByIds()` — delete by single or multiple IDs.
- `save()` — insert-or-update: inserts new entities (back-fills auto-increment PK), updates only dirty fields for existing ones. Emits before/after save events via `EventService`.
- `fetchDataArray()` / `setByDataArray()` — convert between entity object and column-keyed array.

---

## Layer 4 — Query System

### `Query`

A domain object representing a SELECT query. Built programmatically via methods; not tied to any database dialect.

| Method | Purpose |
|---|---|
| `__construct(string\|Query $from)` | Table (class name) or subquery as source |
| `addFields()` / `setFields()` | Columns to select |
| `addCondition()` | WHERE clause fragment + bound variables |
| `addInnerJoin()` / `addJoin()` | JOIN clauses |
| `addGroupBy()` | GROUP BY columns |
| `addOrderBy()` | ORDER BY with direction |
| `setLimit()` | LIMIT offset + count |
| `addVariables()` | Extra bound parameters |

When `from` is a class name and no fields are specified, `fields()` auto-resolves all registered columns from `EntityManager`.

Join types: `INNER_JOIN`, `LEFT_JOIN`, `RIGHT_JOIN`, `OUTER_JOIN`.

### `QueryBuilder` (abstract)

Converts a `Query` object and `EntityManager` metadata into SQL strings. Also generates DDL.

**Key methods:**
- `createTable(className, ifNotExists)` — generates `CREATE TABLE` from entity metadata (column defs, primary key, foreign keys).
- `findAll(Query)` — generates a full `SELECT` with joins, conditions, ordering, and limit.
- `findAllCount(Query)` — generates a `SELECT count(1)` variant.
- `fieldNames()` — handles field aliasing and raw expressions.

Abstract methods that must be provided by each database-specific subclass: `columnDefinition`, `primaryKeyDefinition`, `foreignKeyDefinition`, `isTableExist`, `listTables`, `describeTable`, `columnsByTableDescription`.

### `MariaQueryBuilder`

`QueryBuilder` implementation for MariaDB/MySQL. Maps abstract types to SQL types:

| Abstract type | MariaDB type |
|---|---|
| `int` | `int` |
| `long` | `bigint` |
| `float` | `float` |
| `double` | `double` |
| `numeric` | `decimal(p,s)` |
| `string` (with size) | `varchar(n)` / `char(n)` if fixSize |
| `string` (no size) | `longtext` |
| `bool` | `tinyint(1)` |
| `date` | `date` |
| `time` | `time` |
| `datetime` | `datetime` |
| `blob` | `blob` |

Default value `Column::NOW` for date/time columns maps to `utc_date()`, `utc_time()`, or `utc_timestamp()`.

### `QueryExecutor`

Bridges `Query` → `QueryBuilder` → `Database`. Provides a single clean API for query execution:

| Method | Returns |
|---|---|
| `isTableExist(className)` | `bool` |
| `createTable(className, ifNotExists)` | void |
| `listTables()` | `string[]` |
| `findColumns(className)` | `array` |
| `findAll(Query, fields)` | `array` of associative rows |
| `findAllColumn(Query, column)` | `array` of scalar values |
| `findAllCount(Query)` | scalar count |

---

## Configuration

```ini
database.default.dsn      = mysql:host=localhost
database.default.name     = mydb
database.default.username = root
database.default.password = secret
database.default.table_prefix = app_
```

The `configName` property on a `Database` subclass controls which section is read (default: `"default"`).

Query builder max limit (default 1000):
```ini
entities.query_builder.max_limit = 1000
```

---

## Usage Example

```php
// Define an entity
class User extends Entity {
    #[Column(type: Column::TYPE_INT, autoIncrement: true, primaryKey: true, notNull: true)]
    public int $id;

    #[Column(type: Column::TYPE_STRING, size: 100, notNull: true)]
    public string $name;
}

// Save (insert on first call, update on subsequent)
$user = new User();
$user->name = 'Alice';
$entityManager->save($user); // INSERT; $user->id is back-filled

// Fetch
$user = $entityManager->findById(User::class, 42);

// Query
$query = new Query(User::class);
$query->addCondition('`name` like :name', [':name' => 'Al%']);
$query->addOrderBy('name');
$rows = $queryExecutor->findAll($query);
```