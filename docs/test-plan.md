# dynart-micro-entities — Test Plan

## Test Project

Following the pattern of `dynart-micro-test`, tests live in a separate repository:
**`../dynart-micro-entities-test/`**

`composer.json` requires `dynart/micro-entities` via a path repository (symlinked),
`dynart/micro` for framework interfaces, and `phpunit/phpunit ^9.5`.

---

## Project Structure

```
dynart-micro-entities-test/
├── composer.json
├── phpunit.xml.dist             two suites: "unit" and "integration"
├── configs/
│   └── test.ini                 DB connection config for integration tests
├── src/
│   ├── Entities/
│   │   ├── TestUser.php         id (int PK AI), name (string 100), email (string 150),
│   │   │                        active (bool, default false), created_at (datetime, default NOW)
│   │   └── TestPost.php         id (int PK AI), user_id (int FK→TestUser.id cascade),
│   │                            title (string 200), body (string/longtext), published_at (date)
│   └── TestHelper.php           builds a configured EntityManager + registers test entities
└── tests/
    ├── Unit/
    │   ├── ColumnTest.php
    │   ├── EntityTest.php
    │   ├── QueryTest.php
    │   ├── EntityManagerTest.php
    │   ├── MariaQueryBuilderTest.php
    │   └── ColumnAttributeHandlerTest.php
    └── Integration/
        ├── DatabaseTest.php
        ├── QueryExecutorTest.php
        └── EntityManagerIntegrationTest.php
```

Integration tests require a real MariaDB instance. The suite can be run separately:

```bash
php vendor/bin/phpunit --testsuite unit --stderr
php vendor/bin/phpunit --testsuite integration --stderr
php vendor/bin/phpunit --stderr   # all
```

---

## Unit Tests

No database connection required. PHPUnit mocks the `Database` abstract class wherever
`EntityManager` needs one for construction.

---

### ColumnTest

| # | Test | Assertion |
|---|------|-----------|
| 1 | TYPE_* constants | Each has the expected string value (`'int'`, `'long'`, …, `'blob'`) |
| 2 | ACTION_* constants | `ACTION_CASCADE = 'cascade'`, `ACTION_SET_NULL = 'set_null'` |
| 3 | NOW constant | `NOW = 'now'` |
| 4 | Constructor defaults | size=0, fixSize/notNull/autoIncrement/primaryKey=false, default/foreignKey/onDelete/onUpdate=null |
| 5 | Constructor stores all args | All ten properties read back correctly after full construction |

---

### EntityTest

Concrete subclass `TestUser` used as the `Entity` under test.

| # | Test | Assertion |
|---|------|-----------|
| 1 | Default state | `isNew()` returns `true` |
| 2 | `setNew(false)` | `isNew()` returns `false` |
| 3 | `getDirtyFields` without snapshot | Returns full `$currentData` array |
| 4 | `takeSnapshot` + no change | `getDirtyFields()` returns `[]` |
| 5 | `takeSnapshot` + changed field | `getDirtyFields()` returns only the changed key/value |
| 6 | `takeSnapshot` + added key | Extra key in currentData is reported as dirty |
| 7 | `isDirty` — clean | Returns `false` after snapshot with no changes |
| 8 | `isDirty` — dirty | Returns `true` after a field changes |
| 9 | `clearSnapshot` | `getDirtyFields()` returns all fields again (no snapshot) |
| 10 | `beforeSaveEvent()` | Returns `"<FQCN>.before_save"` |
| 11 | `afterSaveEvent()` | Returns `"<FQCN>.after_save"` |

---

### QueryTest

| # | Test | Assertion |
|---|------|-----------|
| 1 | `from()` — class name | Returns the string passed to constructor |
| 2 | `from()` — subquery | Returns the nested `Query` instance |
| 3 | `fields()` default | Returns `[]` |
| 4 | `addFields()` | Merges into fields array |
| 5 | `setFields()` | Replaces fields array |
| 6 | `addVariables()` | Merges into variables |
| 7 | `addCondition()` without variables | Appends condition string |
| 8 | `addCondition()` with variables | Appends condition and merges variables |
| 9 | `conditions()` | Returns all added conditions |
| 10 | `addInnerJoin()` | Delegates to `addJoin` with `INNER_JOIN` type |
| 11 | `addJoin()` | Appends `[type, from, condition]` tuple |
| 12 | `joins()` | Returns all added joins |
| 13 | `addGroupBy()` | Appends group name |
| 14 | `groupBy()` | Returns all group names |
| 15 | `addOrderBy()` default dir | Appends `['field', 'asc']` |
| 16 | `addOrderBy()` desc | Appends `['field', 'desc']` |
| 17 | `orderBy()` | Returns all order entries |
| 18 | `offset()` / `max()` default | Both return `-1` |
| 19 | `setLimit()` | `offset()` and `max()` return set values |
| 20 | Join type constants | `INNER_JOIN`, `LEFT_JOIN`, `RIGHT_JOIN`, `OUTER_JOIN` have expected string values |

---

### EntityManagerTest

Uses a PHPUnit mock of `Database` (configured to return `''` from `configValue('table_prefix')`).

| # | Test | Assertion |
|---|------|-----------|
| 1 | `addColumn()` | `tableName()` and `tableColumns()` now return registered data |
| 2 | `tableNameByClass()` with prefix | Lowercases simple class name and prepends prefix |
| 3 | `tableNameByClass()` without prefix | Returns lowercased name only |
| 4 | `tableNameByClass()` hash mode | Returns `#ClassName` when `setUseEntityHashName(true)` |
| 5 | `tableName()` unregistered | Throws `EntityManagerException` |
| 6 | `tableColumns()` unregistered | Throws `EntityManagerException` |
| 7 | `tableNames()` | Returns all registered class→tableName entries |
| 8 | `allTableColumns()` | Returns all registered class→columns entries |
| 9 | `primaryKey()` — single | Returns the column name marked `primaryKey=true` |
| 10 | `primaryKey()` — composite | Returns array of column names |
| 11 | `primaryKey()` — none | Returns `null` |
| 12 | `primaryKey()` — cached | Second call returns same result without re-scanning |
| 13 | `isPrimaryKeyAutoIncrement()` — true | PK column has `autoIncrement=true` |
| 14 | `isPrimaryKeyAutoIncrement()` — false | PK column has `autoIncrement=false` |
| 15 | `isPrimaryKeyAutoIncrement()` — composite | Returns `false` |
| 16 | `primaryKeyCondition()` — single | Returns `` `col` = :pkValue `` |
| 17 | `primaryKeyCondition()` — composite | Returns `` `c1` = :pkValue0 and `c2` = :pkValue1 `` |
| 18 | `primaryKeyConditionParams()` — single | Returns `[':pkValue' => $id]` |
| 19 | `primaryKeyConditionParams()` — composite | Returns `[':pkValue0' => $a, ':pkValue1' => $b]` |
| 20 | `primaryKeyValue()` — single | Returns scalar from data array |
| 21 | `primaryKeyValue()` — composite | Returns array of values |
| 22 | `safeTableName()` | Returns escaped table name (backtick-wrapped for Maria) |
| 23 | `fetchDataArray()` | Extracts only registered column properties from entity |
| 24 | `setByDataArray()` | Sets entity properties and takes snapshot |
| 25 | `setByDataArray()` unknown column | Throws `EntityManagerException` |

---

### MariaQueryBuilderTest

Instantiate a real `MariaDatabase` (no connection — `escapeName`/`escapeLike` are pure string
operations) and a real `EntityManager` with `TestUser` and `TestPost` registered.

#### `columnDefinition()`

| # | Column config | Expected SQL fragment |
|---|---------------|-----------------------|
| 1 | `int` no size | `` `col` int `` |
| 2 | `int(11)` | `` `col` int(11) `` |
| 3 | `long` | `` `col` bigint `` |
| 4 | `float` | `` `col` float `` |
| 5 | `double` | `` `col` double `` |
| 6 | `numeric [10,2]` | `` `col` decimal(10, 2) `` |
| 7 | `string` no size | `` `col` longtext `` |
| 8 | `string(100)` | `` `col` varchar(100) `` |
| 9 | `string(10)` fixSize | `` `col` char(10) `` |
| 10 | `bool` | `` `col` tinyint(1) `` |
| 11 | `date` / `time` / `datetime` / `blob` | Correct direct type mapping |
| 12 | `notNull=true` | Appends `not null` |
| 13 | `autoIncrement=true` | Appends `auto_increment` |
| 14 | `default=null` | Appends `default null` |
| 15 | `default='foo'` string | Appends `default 'foo'` (escaped) |
| 16 | `default=true` bool | Appends `default 1` |
| 17 | `default=false` bool | Appends `default 0` |
| 18 | `default=NOW` datetime | Appends `default utc_timestamp()` |
| 19 | `default=NOW` date | Appends `default utc_date()` |
| 20 | `default=NOW` time | Appends `default utc_time()` |
| 21 | `default=['raw()']` | Appends `default raw()` (unquoted) |
| 22 | `blob` with any default | Throws `EntityManagerException` |
| 23 | `string` no size with default | Throws `EntityManagerException` |
| 24 | Unknown type | Throws `EntityManagerException` |
| 25 | Non-int size for int type | Throws `EntityManagerException` |
| 26 | Wrong array size for numeric | Throws `EntityManagerException` |

#### `foreignKeyDefinition()`

| # | Test | Expected |
|---|------|----------|
| 27 | `foreignKey=null` | Returns empty string |
| 28 | Valid FK `[TestUser::class, 'id']` | Correct `foreign key … references …` SQL |
| 29 | With `onDelete=ACTION_CASCADE` | Appends `on delete cascade` |
| 30 | With `onUpdate=ACTION_SET_NULL` | Appends `on update set null` |
| 31 | FK array size != 2 | Throws `EntityManagerException` |
| 32 | Unknown action string | Throws `EntityManagerException` |

#### `primaryKeyDefinition()`

| # | Test | Expected |
|---|------|----------|
| 33 | No PK column | Returns empty string |
| 34 | Single PK | Returns `primary key (\`id\`)` |
| 35 | Composite PK | Returns `primary key (\`a\`, \`b\`)` |

#### `createTable()`

| # | Test | Expected |
|---|------|----------|
| 36 | Basic table | Full `create table \`tbl\` (…)` SQL |
| 37 | `ifNotExists=true` | Includes `if not exists` |
| 38 | With PK | Includes `primary key (…)` line |
| 39 | With FK | Includes `foreign key … references …` line |

#### `findAll()` / `findAllCount()`

| # | Test | Expected SQL fragment |
|---|------|-----------------------|
| 40 | No explicit fields | `select \`id\`, \`name\`, … from \`tbl\`` (all registered columns) |
| 41 | Explicit fields list | Only specified columns |
| 42 | Aliased fields | `` `col` as `alias` `` |
| 43 | Raw expression field `['c' => ['count(1)']]` | `count(1) as \`c\`` |
| 44 | `addCondition()` one | `where (…)` |
| 45 | `addCondition()` two | `where (…) and (…)` |
| 46 | `addInnerJoin()` | `inner join \`tbl2\` on …` |
| 47 | `addJoin()` left join with alias | `left join \`tbl2\` as \`alias\` on …` |
| 48 | `addGroupBy()` | `group by …` |
| 49 | `addOrderBy()` on aliased field | `order by \`alias\` asc` |
| 50 | `addOrderBy()` on non-selected field | Ignored (not included in ORDER BY) |
| 51 | `setLimit()` | `limit 0, 10` |
| 52 | limit max clamped | Exceeding `maxLimit` config clamps to max |
| 53 | limit offset clamp | Negative offset clamped to 0 |
| 54 | limit max < 1 | Clamped to 1 |
| 55 | Subquery as `from` | `from (select …) S1` |
| 56 | `findAllCount()` | Wraps in `select count(1) as \`c\`` |
| 57 | `isTableExist()` SQL | Correct `information_schema.tables` query |
| 58 | `listTables()` SQL | Returns `show tables` |

---

### ColumnAttributeHandlerTest

| # | Test | Assertion |
|---|------|-----------|
| 1 | `attributeClass()` | Returns `Column::class` |
| 2 | `targets()` | Returns `[AttributeHandlerInterface::TARGET_PROPERTY]` |
| 3 | `handle()` — full Column | `EntityManager::tableColumns()` contains the exact `Column` object |
| 4 | `handle()` — minimal Column | Only required `type` set; object is the same instance passed through |

---

## Integration Tests

Require a running MariaDB instance. Config read from `configs/test.ini`. Each test class
creates and drops its tables in `setUp` / `tearDown`.

---

### DatabaseTest

| # | Test | Assertion |
|---|------|-----------|
| 1 | Not connected before first query | `connected()` returns `false` initially |
| 2 | Connected after first query | `connected()` returns `true` |
| 3 | `#ClassName` substitution | `#TestUser` in SQL becomes `<prefix>testuser` |
| 4 | `#ClassName` inside string literal preserved | `'#NotReplaced'` stays unchanged |
| 5 | `query()` returns PDOStatement | Statement is executable |
| 6 | `fetch()` assoc | Returns single row as array |
| 7 | `fetch()` with className | Returns hydrated object of that class |
| 8 | `fetch()` no match | Returns `false` |
| 9 | `fetchAll()` | Returns array of all matching rows |
| 10 | `fetchAll()` with className | Returns array of hydrated objects |
| 11 | `fetchColumn()` | Returns flat array of first-column values |
| 12 | `fetchOne()` | Returns single scalar value |
| 13 | `insert()` + `lastInsertId()` | Row inserted; last insert ID is numeric |
| 14 | `update()` with condition | Only matching rows are updated |
| 15 | `update()` without condition | All rows are updated |
| 16 | `getInConditionAndParams()` | Returns correct IN clause and params map |
| 17 | `beginTransaction()` + `commit()` | Data visible after commit |
| 18 | `beginTransaction()` + `rollBack()` | Data not visible after rollback |
| 19 | `runInTransaction()` success | Callable result is committed |
| 20 | `runInTransaction()` exception | Data is rolled back, exception re-thrown |
| 21 | `escapeLike()` | `%foo%` → `\%foo\%` |

---

### QueryExecutorTest

| # | Test | Assertion |
|---|------|-----------|
| 1 | `isTableExist()` — missing | Returns `false` |
| 2 | `createTable()` | Table exists afterwards |
| 3 | `isTableExist()` — present | Returns `true` |
| 4 | `createTable()` with `ifNotExists=true` | Does not throw when table already exists |
| 5 | `listTables()` | Contains the newly created table name |
| 6 | `findAll()` no conditions | Returns all inserted rows as associative arrays |
| 7 | `findAll()` with condition | Returns only matching rows |
| 8 | `findAllColumn()` | Returns flat array of the specified column |
| 9 | `findAllCount()` | Returns correct integer count |
| 10 | `findColumns()` | Returns column metadata from live DB |

---

### EntityManagerIntegrationTest

| # | Test | Assertion |
|---|------|-----------|
| 1 | `save()` new entity | Row inserted; auto-increment PK back-filled onto entity |
| 2 | `save()` new entity `isNew` flag | Entity is marked not-new after save |
| 3 | `save()` new entity snapshot | Entity snapshot taken; subsequent save with no changes issues no UPDATE |
| 4 | `save()` dirty entity | Only changed columns sent in UPDATE (verify via query log / re-fetch) |
| 5 | `save()` clean entity | No UPDATE query issued when nothing changed |
| 6 | `findById()` | Returns correct entity with all fields populated |
| 7 | `findById()` `isNew` flag | Returned entity is marked not-new |
| 8 | `findById()` snapshot | Returned entity has snapshot; immediate save issues no UPDATE |
| 9 | `save()` emits `before_save` event | EventService receives the event before DB write |
| 10 | `save()` emits `after_save` event | EventService receives the event after DB write |
| 11 | `deleteById()` | Row no longer exists in DB |
| 12 | `deleteByIds()` | All specified rows removed; others untouched |
| 13 | `insert()` | Row inserted; returns last insert ID string |
| 14 | `update()` with condition | Matching row updated |
