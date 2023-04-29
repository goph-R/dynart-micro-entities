<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;
use Dynart\Micro\Database;

class EntityManager {

    const COLUMN_PRIMARY_KEY = 'primaryKey';
    const COLUMN_AUTO_INCREMENT = 'autoIncrement';

    /** @var Config */
    protected $config;

    /** @var Database */
    protected $db;

    /** @var array */
    protected $tableColumns = [];

    /** @var array */
    protected $tableNames = [];

    /** @var array */
    protected $primaryKeys = [];

    protected $tableNamePrefix = '';

    public function __construct(Config $config, Database $db) {
        $this->config = $config;
        $this->db = $db;
        $this->tableNamePrefix = $db->configValue('table_prefix');
    }

    public function addColumn(string $className, string $propertyName, array $columnData) {
        $columnName = strtolower($propertyName);
        if (!array_key_exists($className, $this->tableNames)) {
            $this->tableNames[$className] = $this->createTableNameByClass($className);
            $this->tableColumns[$className] = [];
        }
        if (!array_key_exists($columnName, $this->tableColumns[$className])) {
            $this->tableColumns[$className][$columnName] = [];
        }
        $this->tableColumns[$className][$columnName] = $columnData;
    }

    public function createTableNameByClass(string $className): string {
        return $this->tableNamePrefix.strtolower(substr(strrchr($className, '\\'), 1));
    }

    public function tableNames() {
        return $this->tableNames;
    }

    public function tableName(string $className) {
        if (!array_key_exists($className, $this->tableNames)) {
            throw new EntityManagerException("Table definition doesn't exist for ".$className);
        }
        return $this->tableNames[$className];
    }

    public function tableColumns(string $className) {
        if (!array_key_exists($className, $this->tableColumns)) {
            throw new EntityManagerException("Table definition doesn't exist for ".$className);
        }
        return $this->tableColumns[$className];
    }

    public function primaryKey(string $className) {
        if (array_key_exists($className, $this->primaryKeys)) {
            return $this->primaryKeys[$className];
        }
        $primaryKey = [];
        foreach ($this->tableColumns($className) as $columnName => $column) {
            if ($this->isColumn($column, self::COLUMN_PRIMARY_KEY)) {
                $primaryKey[] = $columnName;
            }
        }
        $result = count($primaryKey) > 1 ? $primaryKey : $primaryKey[0];
        $this->primaryKeys[$className] = $result;
        return $result;
    }

    protected function isColumn(array $column, string $boolName) {
        return array_key_exists($boolName, $column) && $column[$boolName] === true;
    }

    public function primaryKeyValue(string $className, array $data) {
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

    public function primaryKeyConditionParams(string $className, $pkValue): array {
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

    public function isPrimaryKeyAutoIncrement(string $className): string {
        $pkName = $this->primaryKey($className);
        if (is_array($pkName)) { // multi column primary keys can't be auto incremented
            return false;
        }
        $pkColumn = $this->tableColumns[$className][$pkName];
        return $this->isColumn($pkColumn, self::COLUMN_AUTO_INCREMENT);
    }

    public function save(Entity $entity) {
        $className = get_class($entity);
        $tableName = $this->tableName($className);
        $data = $this->fetchDataArray($entity);
        if ($entity->isNew()) {
            $this->db->insert($tableName, $data);
            if ($this->isPrimaryKeyAutoIncrement($className)) {
                $pkName = $this->primaryKey($className);
                $entity->$pkName = $this->db->lastInsertId();
            }
        } else {
            $this->db->update(
                $tableName, $data,
                $this->primaryKeyCondition($className),
                $this->primaryKeyConditionParams($className, $this->primaryKeyValue($className, $data))
            );
        }
    }

    protected function fetchDataArray(Entity $entity) {
        $columnKeys = array_keys($this->tableColumns(get_class($entity)));
        $data = [];
        foreach ($columnKeys as $ck) {
            $data[$ck] = $entity->$ck;
        }
        return $data;
    }

    public function findById(string $className, $id) {
        $sql = "select * from !tableName where !conditions";
        $params = array_merge($this->primaryKeyConditionParams($className, $id), [
            '!tableName'  => $this->db->escapeName($this->tableName($className)),
            '!conditions' => $this->primaryKeyCondition($className)
        ]);
        $result = $this->db->fetch($sql, $params, $className);
        $result->setNew(false);
        return $result;
    }
}
