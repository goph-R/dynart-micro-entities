<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\ConfigInterface;
use Dynart\Micro\EventServiceInterface;

class EntityManager {

    const COLUMN_TYPE = 'type';
    const COLUMN_SIZE = 'size';
    const COLUMN_FIX_SIZE = 'fixSize';
    const COLUMN_NOT_NULL = 'notNull';
    const COLUMN_AUTO_INCREMENT = 'autoIncrement';
    const COLUMN_DEFAULT = 'default';
    const COLUMN_PRIMARY_KEY = 'primaryKey';
    const COLUMN_FOREIGN_KEY = 'foreignKey';
    const COLUMN_ON_DELETE = 'onDelete';
    const COLUMN_ON_UPDATE = 'onUpdate';

    const TYPE_INT = 'int';
    const TYPE_LONG = 'long';
    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_NUMERIC = 'numeric';
    const TYPE_STRING = 'string';
    const TYPE_BOOL = 'bool';
    const TYPE_DATE = 'date';
    const TYPE_TIME = 'time';
    const TYPE_DATETIME = 'datetime';
    const TYPE_BLOB = 'blob';

    const DEFAULT_NOW = 'now';

    const ACTION_CASCADE = 'cascade';
    const ACTION_SET_NULL = 'set_null';

    protected array $tableColumns = [];
    protected array $tableNames = [];
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

    public function addColumn(string $className, string $columnName, array $columnData): void {
        if (!array_key_exists($className, $this->tableNames)) {
            $this->tableNames[$className] = $this->tableNameByClass($className);
            $this->tableColumns[$className] = [];
        }
        $this->tableColumns[$className][$columnName] = $columnData;
    }

    public function tableNameByClass(string $className, bool $withPrefix = true): string {
        $simpleClassName = $this->simpleClassName($className);
        if ($this->useEntityHashName) {
            return '#'.$simpleClassName;
        }
        return ($withPrefix ? $this->tableNamePrefix : '').strtolower($simpleClassName);
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
            if ($this->isColumn($column, self::COLUMN_PRIMARY_KEY)) {
                $primaryKey[] = $columnName;
            }
        }
        $result = empty($primaryKey) ? null : (count($primaryKey) > 1 ? $primaryKey : $primaryKey[0]);
        $this->primaryKeys[$className] = $result;
        return $result;
    }

    public function isColumn(array $column, string $name): bool {
        return array_key_exists($name, $column) && $column[$name] === true;
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
        return $this->isColumn($pkColumn, self::COLUMN_AUTO_INCREMENT);
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

    public function findById(string $className, mixed $id): Entity {
        $condition = $this->primaryKeyCondition($className);
        $safeTableName = $this->safeTableName($className);
        $sql = "select * from $safeTableName where $condition";
        $params = $this->primaryKeyConditionParams($className, $id);
        $result = $this->db->fetch($sql, $params, $className);
        $result->setNew(false);
        $result->takeSnapshot($this->fetchDataArray($result));
        return $result;
    }

    public function deleteById(string $className, int $id): void {
        $sql = "delete from {$this->safeTableName($className)} where id = :id limit 1";
        $this->db->query($sql, [':id' => $id]);
    }

    public function deleteByIds(string $className, array $ids): void {
        [$condition, $params] = $this->db->getInConditionAndParams($ids);
        $sql = "delete from {$this->safeTableName($className)} where id in ($condition)";
        $this->db->query($sql, $params);
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
            if (!array_key_exists($n, $columnKeys)) {
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
