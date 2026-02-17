# dynart-micro-entities

PDO-based ORM / entity library for the [dynart-micro](https://github.com/goph-R/dynart-micro) framework.

Provides database abstraction, PHP 8 attribute-driven entity metadata, query building, and full CRUD with dirty-field tracking.

- Namespace: `Dynart\Micro\Entities`
- PHP 8.0+, PSR-4 from `src/`
- Requires: `dynart/micro`, `ext-pdo`, `ext-json`

## Installation

```bash
composer require dynart/micro-entities
```

## Quick Start

### 1. Define an entity

```php
use Dynart\Micro\Entities\Entity;
use Dynart\Micro\Entities\Attribute\Column;

class User extends Entity {

    #[Column(type: Column::TYPE_INT, primaryKey: true, autoIncrement: true, notNull: true)]
    public int $id = 0;

    #[Column(type: Column::TYPE_STRING, size: 100, notNull: true)]
    public string $name = '';

    #[Column(type: Column::TYPE_STRING, size: 150)]
    public string $email = '';

    #[Column(type: Column::TYPE_BOOL, default: false)]
    public bool $active = false;

    #[Column(type: Column::TYPE_DATETIME, default: Column::NOW)]
    public ?string $created_at = null;
}
```

### 2. Configure the database

```ini
; config.ini
database.default.dsn      = "mysql:host=localhost"
database.default.name     = mydb
database.default.username = root
database.default.password = secret
database.default.table_prefix = app_
```

> **Note:** Quote the DSN value if it contains `=` (e.g. `host=localhost`), because
> `parse_ini_file` with `INI_SCANNER_TYPED` treats bare `=` inside values as a syntax error.

### 3. Wire up services

Register the services in your dynart-micro application bootstrap:

```php
Micro::add(Database::class, MariaDatabase::class);
Micro::add(QueryBuilder::class, MariaQueryBuilder::class);
Micro::add(EntityManager::class);
Micro::add(QueryExecutor::class);
Micro::add(AttributeHandlerInterface::class, ColumnAttributeHandler::class);
```

Enable attribute-based entity discovery:

```php
$app->useRouteAnnotations(); // registers AttributeProcessor middleware
```

Or register entities manually:

```php
$entityManager->addColumn(User::class, 'id',    new Column(type: Column::TYPE_INT, primaryKey: true, autoIncrement: true, notNull: true));
$entityManager->addColumn(User::class, 'name',  new Column(type: Column::TYPE_STRING, size: 100, notNull: true));
// ...
```

### 4. CRUD

```php
// Insert
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@example.com';
$entityManager->save($user);   // INSERT; $user->id is back-filled automatically

// Update (only dirty fields are sent)
$user->name = 'Bob';
$entityManager->save($user);   // UPDATE SET name = 'Bob'

// Find by primary key
$user = $entityManager->findById(User::class, 42);

// Delete
$entityManager->deleteById(User::class, 42);
$entityManager->deleteByIds(User::class, [1, 2, 3]);
```

### 5. Querying

```php
use Dynart\Micro\Entities\Query;

$query = new Query(User::class);
$query->addCondition('`active` = :active', [':active' => 1]);
$query->addOrderBy('name');       // works with aliased fields
$query->setLimit(0, 20);

$rows  = $queryExecutor->findAll($query);       // array of assoc arrays
$count = $queryExecutor->findAllCount($query);  // integer
$names = $queryExecutor->findAllColumn($query, 'name'); // flat array
```

### 6. Schema management

```php
// Create table from entity metadata
$queryExecutor->createTable(User::class);
$queryExecutor->createTable(User::class, ifNotExists: true);

// Inspect
$queryExecutor->isTableExist(User::class);  // bool
$queryExecutor->listTables();               // string[]
```

---

## Architecture

```
#[Column] attributes on Entity subclasses
        ↓ processed by
ColumnAttributeHandler → EntityManager   (metadata registry)
                               ↓
                          Database        (PDO wrapper, lazy connect)
                               ↓
              QueryBuilder → QueryExecutor (query / DDL execution)
```

### Entity

`Entity` (abstract) is the base for all persistent objects. It provides:

- **new/persisted flag** — `isNew()` / `setNew()`
- **dirty tracking** — `takeSnapshot()` / `getDirtyFields()` / `isDirty()` / `clearSnapshot()`
- **event names** — `beforeSaveEvent()` / `afterSaveEvent()` return strings like `User.before_save`

### Column attribute

All column metadata lives on the property's `#[Column]` attribute:

| Parameter | Type | Description |
|---|---|---|
| `type` | string | One of the `TYPE_*` constants |
| `size` | int\|array | Column size; `[precision, scale]` for `numeric` |
| `fixSize` | bool | Use `CHAR` instead of `VARCHAR` for strings |
| `notNull` | bool | `NOT NULL` constraint |
| `autoIncrement` | bool | Auto-increment |
| `primaryKey` | bool | Part of the primary key |
| `default` | mixed | Default value; `Column::NOW` for UTC timestamps; wrap in `[]` for raw SQL |
| `foreignKey` | array\|null | `[TargetClass::class, 'column']` |
| `onDelete` | string\|null | `Column::ACTION_CASCADE` or `Column::ACTION_SET_NULL` |
| `onUpdate` | string\|null | `Column::ACTION_CASCADE` or `Column::ACTION_SET_NULL` |

**Type constants:**

| Constant | MariaDB type |
|---|---|
| `TYPE_INT` | `int` |
| `TYPE_LONG` | `bigint` |
| `TYPE_FLOAT` | `float` |
| `TYPE_DOUBLE` | `double` |
| `TYPE_NUMERIC` | `decimal(p, s)` |
| `TYPE_STRING` (with size) | `varchar(n)` / `char(n)` if `fixSize` |
| `TYPE_STRING` (no size) | `longtext` |
| `TYPE_BOOL` | `tinyint(1)` |
| `TYPE_DATE` | `date` |
| `TYPE_TIME` | `time` |
| `TYPE_DATETIME` | `datetime` |
| `TYPE_BLOB` | `blob` |

### Database

`Database` (abstract) wraps PDO with:

- **Lazy connection** — connects on the first `query()` call
- **`#ClassName` substitution** — `#User` in SQL is replaced with `<prefix>user` (outside string literals)
- **Parameterized queries** — all methods accept `params` arrays
- **Fetch helpers** — `fetch`, `fetchAll`, `fetchColumn`, `fetchOne` with optional class hydration
- **DML helpers** — `insert`, `update` with automatic name escaping
- **Transaction helpers** — `beginTransaction`, `commit`, `rollBack`, `runInTransaction`

`MariaDatabase` is the MySQL/MariaDB implementation. `PdoBuilder` is a fluent factory injected into `Database` to keep construction testable.

### EntityManager

Central registry. Key operations:

| Method | Description |
|---|---|
| `save(Entity)` | Insert or update; back-fills auto-increment PK; emits save events |
| `findById(class, id)` | Fetch by PK; marks entity not-new; takes a dirty-tracking snapshot |
| `deleteById(class, id)` | Delete single row by PK |
| `deleteByIds(class, ids[])` | Delete multiple rows by PK |
| `insert(class, data[])` | Raw insert; returns last insert ID |
| `update(class, data[], cond, params)` | Raw update with condition |
| `fetchDataArray(Entity)` | Extract column values from entity into an array |
| `setByDataArray(Entity, data[])` | Set entity properties from array and take snapshot |

### Query / QueryBuilder / QueryExecutor

- **`Query`** — a plain data object describing a SELECT (fields, conditions, joins, group, order, limit)
- **`QueryBuilder`** — converts Query + EntityManager metadata to SQL strings; also generates DDL
- **`QueryExecutor`** — executes queries through Database and returns results

---

## Foreign Keys

```php
class Post extends Entity {

    #[Column(type: Column::TYPE_INT, primaryKey: true, autoIncrement: true, notNull: true)]
    public int $id = 0;

    #[Column(
        type: Column::TYPE_INT,
        notNull: true,
        foreignKey: [User::class, 'id'],
        onDelete: Column::ACTION_CASCADE
    )]
    public int $user_id = 0;
}
```

`createTable(Post::class)` generates the `FOREIGN KEY … REFERENCES …` clause automatically. Always create the referenced table first.

---

## Composite Primary Keys

```php
class UserRole extends Entity {

    #[Column(type: Column::TYPE_INT, primaryKey: true, notNull: true)]
    public int $user_id = 0;

    #[Column(type: Column::TYPE_INT, primaryKey: true, notNull: true)]
    public int $role_id = 0;
}
```

`EntityManager::primaryKey()` returns an array for composite keys. `save()`, `findById()`, and `deleteById()` all accept an array as the ID argument in this case.

---

## Configuration Reference

```ini
; Database connection (configName = "default")
database.default.dsn           = "mysql:host=localhost"
database.default.name          = mydb
database.default.username      = root
database.default.password      = secret
database.default.table_prefix  = app_

; Query builder
entities.query_builder.max_limit = 1000
```

Multiple database connections are supported by changing the `configName` property on a `Database` subclass, which shifts all config key reads to a different prefix (e.g. `database.reports.*`).

---

## Testing

The test suite lives in the separate repository **[dynart-micro-entities-test](https://github.com/goph-R/dynart-micro-entities-test)**, which symlinks this library via a Composer path repository.

```bash
# Unit tests (no DB required)
php vendor/bin/phpunit --testsuite unit --stderr

# Integration tests (requires MariaDB)
php vendor/bin/phpunit --testsuite integration --stderr
```

See the test project's [README](https://github.com/goph-R/dynart-micro-entities-test/README.md) for setup instructions.