<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\ConfigInterface;
use Dynart\Micro\EventServiceInterface;
use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Attribute\Table;

class EntityManager {

    protected array $tableColumns = [];
    protected array $tableNames = [];
    protected array $tables = [];
    protected array $primaryKeys = [];
    protected string $tableNamePrefix = '';
    protected bool $useEntityHashName = false;

    public function __construct(
        protected ConfigInterface $config,
        protected Database $db,
        protected EventServiceInterface $events,
    ) {
        $this->tableNamePrefix = $db->configValue('table_prefix');
    }

    public function setUseEntityHashName(bool $value): void {
        $this->useEntityHashName = $value;
    }

    public function addColumn(string $className, string $columnName, Column $column): void {
        if (!array_key_exists($className, $this->tableNames)) {
            $this->tableNames[$className] = $this->tableNameByClass($className);
            $this->tableColumns[$className] = [];
        }
        $this->tableColumns[$className][$columnName] = $column;
    }

    /**
     * Registers the table level metadata of an entity
     *
     * The attribute processor handles class level attributes before the property level ones, but
     * the cached table name is refreshed anyway so the order can not matter.
     */
    public function addTable(string $className, Table $table): void {
        $this->tables[$className] = $table;
        if (array_key_exists($className, $this->tableNames)) {
            $this->tableNames[$className] = $this->tableNameByClass($className);
        }
    }

    public function table(string $className): ?Table {
        return $this->tables[$className] ?? null;
    }

    public function tableNameByClass(string $className, bool $withPrefix = true): string {
        $table = $this->tables[$className] ?? null;
        $name = $table !== null && $table->name ? $table->name : $this->simpleClassName($className);
        if ($this->useEntityHashName) {
            return '#'.$name;
        }
        return ($withPrefix ? $this->tableNamePrefix : '').strtolower($name);
    }

    /**
     * Returns with the unique constraints of a table
     *
     * Merges the single column ones declared with `#[Column(unique: true)]` and the composite
     * ones declared with `#[Table(unique: [...])]`.
     *
     * @return array A list of ['name' => ?string, 'columns' => string[]]
     */
    public function uniqueConstraints(string $className): array {
        return $this->constraints($className, 'unique');
    }

    /**
     * Returns with the indexes of a table
     *
     * @see uniqueConstraints()
     * @return array A list of ['name' => ?string, 'columns' => string[]]
     */
    public function indexes(string $className): array {
        return $this->constraints($className, 'index');
    }

    protected function constraints(string $className, string $kind): array {
        $result = [];
        foreach ($this->tableColumns($className) as $columnName => $column) {
            if ($column->$kind) {
                $result[] = ['name' => null, 'columns' => [$columnName]];
            }
        }
        $table = $this->table($className);
        if ($table !== null) {
            foreach ($table->$kind as $name => $columns) {
                $result[] = ['name' => is_string($name) ? $name : null, 'columns' => (array)$columns];
            }
        }
        return $result;
    }

    protected function simpleClassName(string $fullClassName): string {
        return substr(strrchr($fullClassName, '\\'), 1);
    }

    public function tableNames(): array {
        return $this->tableNames;
    }

    public function tableName(string $className): string {
        if (!array_key_exists($className, $this->tableNames)) {
            throw new EntityManagerException("Table definition doesn't exist for ".$className);
        }
        return $this->tableNames[$className];
    }

    public function tableColumns(string $className): array {
        if (!array_key_exists($className, $this->tableColumns)) {
            throw new EntityManagerException("Table definition doesn't exist for ".$className);
        }
        return $this->tableColumns[$className];
    }

    public function primaryKey(string $className): string|array|null {
        if (array_key_exists($className, $this->primaryKeys)) {
            return $this->primaryKeys[$className];
        }
        $primaryKey = [];
        foreach ($this->tableColumns($className) as $columnName => $column) {
            if ($column->primaryKey) {
                $primaryKey[] = $columnName;
            }
        }
        $result = empty($primaryKey) ? null : (count($primaryKey) > 1 ? $primaryKey : $primaryKey[0]);
        $this->primaryKeys[$className] = $result;
        return $result;
    }

    public function primaryKeyValue(string $className, array $data): mixed {
        $primaryKey = $this->primaryKey($className);
        if (is_array($primaryKey)) {
            $result = [];
            foreach ($primaryKey as $pk) {
                $result[] = $data[$pk];
            }
            return $result;
        } else {
            return $data[$primaryKey];
        }
    }

    public function primaryKeyCondition(string $className): string {
        $primaryKey = $this->primaryKey($className);
        if (is_array($primaryKey)) {
            $conditions = [];
            foreach ($primaryKey as $i => $pk) {
                $conditions[] = $this->db->escapeName($pk).' = :pkValue'.$i;
            }
            return join(' and ', $conditions);
        } else {
            return $this->db->escapeName($primaryKey).' = :pkValue';
        }
    }

    public function primaryKeyConditionParams(string $className, mixed $pkValue): array {
        $result = [];
        $primaryKey = $this->primaryKey($className);
        if (is_array($primaryKey) && is_array($pkValue)) {
            foreach ($pkValue as $i => $v) {
                $result[':pkValue'.$i] = $v;
            }
        } else {
            $result[':pkValue'] = $pkValue;
        }
        return $result;
    }

    public function isPrimaryKeyAutoIncrement(string $className): bool {
        $pkName = $this->primaryKey($className);
        if (is_array($pkName)) { // multi-column primary keys can't be auto incremented
            return false;
        }
        $pkColumn = $this->tableColumns[$className][$pkName];
        return $pkColumn->autoIncrement;
    }

    public function safeTableName(string $className, bool $withPrefix = true): string {
        return $this->db->escapeName($this->tableNameByClass($className, $withPrefix));
    }

    public function allTableColumns(): array {
        return $this->tableColumns;
    }

    public function insert(string $className, array $data): string|false {
        $this->db->insert($this->tableName($className), $data);
        return $this->db->lastInsertId();
    }

    public function update(string $className, array $data, string $condition = '', array $conditionParams = []): void {
        $this->db->update($this->tableName($className), $data, $condition, $conditionParams);
    }

    public function findById(string $className, mixed $id): ?Entity {
        $condition = $this->primaryKeyCondition($className);
        $safeTableName = $this->safeTableName($className);
        $sql = "select * from $safeTableName where $condition";
        $params = $this->primaryKeyConditionParams($className, $id);
        $result = $this->db->fetch($sql, $params, $className);
        if (!$result instanceof Entity) {
            return null;
        }
        $result->setNew(false);
        $result->takeSnapshot($this->fetchDataArray($result));
        return $result;
    }

    /**
     * Fetches multiple entities by their primary key values
     *
     * Only works with single column primary keys.
     *
     * @return Entity[]
     */
    public function findByIds(string $className, array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $safePk = $this->db->escapeName($this->singleColumnPrimaryKey($className));
        [$condition, $params] = $this->db->getInConditionAndParams($ids);
        $sql = "select * from {$this->safeTableName($className)} where $safePk in ($condition)";
        $result = [];
        foreach ($this->db->fetchAll($sql, $params, $className) as $entity) {
            $entity->setNew(false);
            $entity->takeSnapshot($this->fetchDataArray($entity));
            $result[] = $entity;
        }
        return $result;
    }

    /**
     * Deletes an entity by its primary key value
     *
     * The entity is loaded before the delete so the before/after delete events carry its full
     * previous state, which is what an auditing listener needs. Does nothing when the row is
     * not found.
     */
    public function deleteById(string $className, mixed $id): void {
        $entity = $this->findById($className, $id);
        if ($entity === null) {
            return;
        }
        $this->events->emit($entity->beforeDeleteEvent(), [$entity]);
        $sql = "delete from {$this->safeTableName($className)} where {$this->primaryKeyCondition($className)} limit 1";
        $this->db->query($sql, $this->primaryKeyConditionParams($className, $id), true);
        $this->markDeleted($entity);
        $this->events->emit($entity->afterDeleteEvent(), [$entity]);
    }

    /**
     * Deletes multiple entities by their primary key values
     *
     * Like `deleteById()` the rows are loaded first so the events carry the previous state, which
     * costs one extra select for the whole batch. Only works with single column primary keys.
     */
    public function deleteByIds(string $className, array $ids): void {
        if (empty($ids)) {
            return;
        }
        $entities = $this->findByIds($className, $ids);
        if (empty($entities)) {
            return;
        }
        foreach ($entities as $entity) {
            $this->events->emit($entity->beforeDeleteEvent(), [$entity]);
        }
        $safePk = $this->db->escapeName($this->singleColumnPrimaryKey($className));
        [$condition, $params] = $this->db->getInConditionAndParams($ids);
        $sql = "delete from {$this->safeTableName($className)} where $safePk in ($condition)";
        $this->db->query($sql, $params, true);
        foreach ($entities as $entity) {
            $this->markDeleted($entity);
            $this->events->emit($entity->afterDeleteEvent(), [$entity]);
        }
    }

    /**
     * Returns with the primary key name, throws when the entity has a composite primary key
     */
    protected function singleColumnPrimaryKey(string $className): string {
        $primaryKey = $this->primaryKey($className);
        if (is_array($primaryKey)) {
            throw new EntityManagerException("Composite primary keys are not supported here: $className");
        }
        if ($primaryKey === null) {
            throw new EntityManagerException("Primary key doesn't exist for $className");
        }
        return $primaryKey;
    }

    /**
     * Puts an entity back into the "not persisted" state after its row was deleted
     */
    protected function markDeleted(Entity $entity): void {
        $entity->setNew(true);
        $entity->clearSnapshot();
    }

    public function save(Entity $entity): void {
        $this->events->emit($entity->beforeSaveEvent(), [$entity]);
        $className = get_class($entity);
        $tableName = $this->tableName($className);
        $data = $this->fetchDataArray($entity);
        if ($entity->isNew()) {
            $this->db->insert($tableName, $data);
            if ($this->isPrimaryKeyAutoIncrement($className)) {
                $pkName = $this->primaryKey($className);
                $entity->$pkName = $this->db->lastInsertId();
                $data[$pkName] = $entity->$pkName;
            }
            $entity->setNew(false);
            $entity->takeSnapshot($data);
        } else {
            $dirtyData = $entity->getDirtyFields($data);
            if ($dirtyData !== []) {
                $this->db->update(
                    $tableName, $dirtyData,
                    $this->primaryKeyCondition($className),
                    $this->primaryKeyConditionParams($className, $this->primaryKeyValue($className, $data))
                );
                $entity->takeSnapshot($data);
            }
        }
        $this->events->emit($entity->afterSaveEvent(), [$entity]);
    }

    public function setByDataArray(Entity $entity, array $data): void {
        $className = get_class($entity);
        $columnKeys = array_keys($this->tableColumns($className));
        foreach ($data as $n => $v) {
            if (!in_array($n, $columnKeys)) {
                throw new EntityManagerException("Column '$n' doesn't exist in $className");
            }
            $entity->$n = $v;
        }
        $entity->takeSnapshot($this->fetchDataArray($entity));
    }

    public function fetchDataArray(Entity $entity): array {
        $columnKeys = array_keys($this->tableColumns(get_class($entity)));
        $data = [];
        foreach ($columnKeys as $ck) {
            $data[$ck] = $entity->$ck;
        }
        return $data;
    }
}
