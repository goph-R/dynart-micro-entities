<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;
use Dynart\Micro\Database;

class EntityManager {

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Database
     */
    protected $database;

    /**
     * @var array
     */
    protected $tables;

    public function __construct(Config $config, Database $database) {
        $this->config = $config;
        $this->db = $database;
    }

    public function addColumn($className, $propertyName, $columnJsonData) {
        $tableName = strtolower($className);
        $columnName = strtolower($propertyName);
        $columnData = json_decode($columnJsonData);
        if (!array_key_exists($tableName, $this->tables)) {
            $this->tables[$tableName] = [];
        }
        if (!array_key_exists($columnName, $this->tables[$tableName])) {
            $this->tables[$tableName][$columnName] = [];
        }
        $this->tables[$tableName][$columnName] = $columnData;
    }

    // TODO: multiple column primary keys (PK name and value is an array then)

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

    public function isNew($entity, $tableName) {
        return $entity->id === null;
    }

    // TODO end

    public function isPrimaryKeyAutoIncrement(string $tableName): string {
        $pkName = $this->primaryKeyName($tableName);
        if (is_array($pkName)) {
            return false;
        }
        $pkColumn = $this->tables[$tableName][$pkName];
        return array_key_exists('autoIncrement', $pkColumn) ? $pkColumn['autoIncrement'] : false;
    }

    public function tableNameByClass(string $className) {
        return strtolower(substr(strrchr($className, '\\'), 1));
    }

    // example: $entityManager->save($user)

    /**
     * @param $entity
     * @param bool $inTransaction
     * @throws \Exception
     */
    public function save($entity, bool $inTransaction = true) {
        $tableName = $this->tableNameByClass(get_class($entity));
        if (!array_key_exists($tableName, $this->tables)) {
            throw new EntityManagerException("Entity type doesn't exists: $tableName");
        }
        if ($inTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->saveEntity($entity, $tableName);
            if ($inTransaction) {
                $this->db->commit();
            }
        } catch (\Exception $e) {
            if ($inTransaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    protected function saveEntity($entity, $tableName) {
        $isNew = $this->isNew($entity, $tableName);
        if (method_exists($entity, 'beforeSave')) {
            $entity->beforeSave($isNew);
        }
        $data = $this->fetchDataArray($entity, $tableName);
        if ($isNew) {
            $this->db->insert($tableName, $data);
            if ($this->isPrimaryKeyAutoIncrement($tableName)) {
                $pkName = $this->primaryKeyName($tableName);
                $entity->$pkName = $this->db->lastInsertId();
            }
        } else {
            $this->db->update(
                $tableName,
                $data,
                $this->primaryKeyCondition($tableName),
                $this->primaryKeyConditionParams($tableName, $this->primaryKeyValue($tableName, $data))
            );
        }
        if (method_exists($entity, 'afterSave')) {
            $entity->afterSave($isNew);
        }
    }

    protected function fetchDataArray($entity, $tableName) {
        $columnKeys = array_keys($this->tables[$tableName]);
        $data = [];
        foreach ($columnKeys as $ck) {
            $data[$ck] = $entity->$ck;
        }
        return $data;
    }

    // example: $entityManager->findById(User::class, 123)

    public function findById(string $className, $id) {
        $tableName = $this->tableNameByClass($className);
        $sql = "select * from ".$this->db->escapeName($tableName)." where ";
        $sql .= $this->primaryKeyCondition($tableName);
        return $this->db->fetch($sql, $this->primaryKeyConditionParams($tableName, $pkValue), $className); // TODO: fetch with object type
    }
}
