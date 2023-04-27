<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;
use Dynart\Micro\Database;

class EntityManager {

    /** @var Config */
    protected $config;

    /** @var Database */
    protected $db;

    /** @var array */
    protected $tables = [];

    protected $tableNamePrefix = '';

    public function __construct(Config $config, Database $db) {
        $this->config = $config;
        $this->db = $db;
        $this->tableNamePrefix = $db->configValue('table_prefix');
    }

    public function addColumn(string $className, string $propertyName, array $columnData) {
        $tableName = $this->tableNameByClass($className);
        $columnName = strtolower($propertyName);
        if (!array_key_exists($tableName, $this->tables)) {
            $this->tables[$tableName] = [];
        }
        if (!array_key_exists($columnName, $this->tables[$tableName])) {
            $this->tables[$tableName][$columnName] = [];
        }
        $this->tables[$tableName][$columnName] = $columnData;
    }

    /*
     * TODO: multiple column primary keys (PK name and value is an array then)
     */

    public function primaryKeyValue(string $tableName, array $data) {
        return $data['id'];
    }

    public function primaryKeyCondition(string $tableName): string {
        return 'id = :pkValue';
    }

    public function primaryKeyConditionParams(string $tableName, $pkValue): array {
        return [':pkValue' => $pkValue];
    }

    public function primaryKeyName(string $tableName): string {
        return 'id';
    }

    /*
     * /TODO
     */

    public function isPrimaryKeyAutoIncrement(string $tableName): string {
        $pkName = $this->primaryKeyName($tableName);
        if (is_array($pkName)) {
            return false;
        }
        $pkColumn = $this->tables[$tableName][$pkName];
        return array_key_exists('autoIncrement', $pkColumn) ? $pkColumn['autoIncrement'] : false;
    }

    public function tableNameByClass(string $className): string {
        return strtolower(substr(strrchr($className, '\\'), 1));
    }

    public function save(Entity $entity) {
        $tableName = $this->tableNameByClass(get_class($entity));
        if (!array_key_exists($tableName, $this->tables)) {
            throw new EntityManagerException("Entity type doesn't exists: $tableName");
        }
        $data = $this->fetchDataArray($entity, $tableName);
        if ($entity->isNew) {
            $this->db->insert($tableName, $data);
            if ($this->isPrimaryKeyAutoIncrement($tableName)) {
                $pkName = $this->primaryKeyName($tableName);
                $entity->$pkName = $this->db->lastInsertId();
            }
        } else {
            $this->db->update(
                $tableName, $data,
                $this->primaryKeyCondition($tableName),
                $this->primaryKeyConditionParams($tableName, $this->primaryKeyValue($tableName, $data))
            );
        }
    }

    protected function fetchDataArray(Entity $entity, $tableName) {
        $columnKeys = array_keys($this->tables[$tableName]);
        $data = [];
        foreach ($columnKeys as $ck) {
            $data[$ck] = $entity->$ck;
        }
        return $data;
    }

    public function findById(string $className, $id) {
        $tableName = $this->tableNameByClass($className);
        $sql = "select * from ".$this->db->escapeName($this->tableNamePrefix.$tableName)." where ";
        $sql .= $this->primaryKeyCondition($tableName);
        $result = $this->db->fetch($sql, $this->primaryKeyConditionParams($tableName, $id), $className);
        $result->isNew = false;
        return $result;
    }

    public function tables() {
        return $this->tables;
    }
}
