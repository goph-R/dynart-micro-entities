# CLAUDE.md

## Project Overview

**dynart-micro-entities** is an entity/ORM library for the [dynart-micro](../dynart-micro) framework. It provides PDO-based database access, entity metadata management, query building, and CRUD operations. Namespace `Dynart\Micro\Entities`, PSR-4 from `src/`.

Depends on `dynart/micro` via Composer path repository (symlinked from `../dynart-micro`).

## Architecture

### Database Layer

`Database` (abstract) → `MariaDatabase` (MySQL/MariaDB implementation). Wraps PDO with lazy connection, prepared statements, logging, and transaction support. `PdoBuilder` constructs PDO instances via fluent API.

Key feature: `#ClassName` syntax in SQL strings is automatically replaced with the prefixed table name (e.g., `#User` → `prefix_user`).

Config keys use `database.{configName}.{key}` pattern (e.g., `database.default.dsn`).

### Entity System

`Entity` (abstract base) — subclasses define public properties matching database columns. Tracks new/persisted state. Provides lifecycle event names (`EVENT_BEFORE_SAVE`, `EVENT_AFTER_SAVE`) for EventService pub/sub.

`EntityManager` — central registry mapping entity classes to tables/columns. Stores metadata from `ColumnAnnotation` processing. Handles `insert`, `update`, `save`, `findById`, `deleteById`. Emits before/after save events.

`ColumnAnnotation` — processes `@column` PHPDoc annotations to register column metadata (type, size, primaryKey, foreignKey, autoIncrement, etc.) into EntityManager.

### Query System

`Query` — fluent query object representing a SELECT (fields, joins, conditions, groups, orders, limit). Supports nested subqueries.

`QueryBuilder` (abstract) → `MariaQueryBuilder` — converts Query objects and EntityManager metadata into SQL strings. Also generates CREATE TABLE DDL from entity metadata.

`QueryExecutor` — executes Query objects against the database via QueryBuilder + Database.

### Class Relationships

```
ColumnAnnotation → EntityManager (registers metadata)
Entity (base for persistent objects)
EntityManager → Database, EventService
Database (abstract) → MariaDatabase → PdoBuilder
QueryBuilder (abstract) → MariaQueryBuilder
QueryExecutor → QueryBuilder, Database, EntityManager
```

## Key Patterns

- **Abstract + Concrete**: Database/MariaDatabase, QueryBuilder/MariaQueryBuilder — designed for multiple DB backends
- **Query Object Pattern**: Query is a domain representation; QueryBuilder generates SQL; QueryExecutor runs it
- **Metadata Registry**: EntityManager stores column/table mappings from annotations
- **Lazy Connection**: Database connects on first query, not on construction
- **Entity Hash Names**: `#ClassName` in SQL → prefixed table name (regex replacement)

## Current State

- Uses **old dynart-micro API** (concrete classes `Config`, `Logger`, `EventService` instead of interfaces `ConfigInterface`, `LoggerInterface`, `EventServiceInterface`)
- **No PHP 8 modernization** yet (PHPDoc types instead of native types, no constructor property promotion)
- **No interfaces** extracted for its own services (Database, EntityManager, QueryBuilder, etc.)
- **No test project** exists yet (unlike dynart-micro which has `../dynart-micro-test/`)
- Composer requires `php >= 7.1.0` (not yet bumped to 8.0+)

## Configuration

Database config in INI format:
```ini
database.default.dsn = mysql:host=localhost
database.default.username = root
database.default.password = secret
database.default.database = mydb
database.default.table_prefix = app_
```

## EntityManager Constants

Column attributes: `COLUMN_TYPE`, `COLUMN_SIZE`, `COLUMN_NOT_NULL`, `COLUMN_AUTO_INCREMENT`, `COLUMN_PRIMARY_KEY`, `COLUMN_FOREIGN_KEY`, `COLUMN_DEFAULT`, `COLUMN_ON_DELETE`, `COLUMN_ON_UPDATE`

Data types: `TYPE_INT`, `TYPE_LONG`, `TYPE_FLOAT`, `TYPE_DOUBLE`, `TYPE_NUMERIC`, `TYPE_STRING`, `TYPE_BOOL`, `TYPE_DATE`, `TYPE_TIME`, `TYPE_DATETIME`, `TYPE_BLOB`
