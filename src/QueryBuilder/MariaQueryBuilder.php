<?php

namespace Dynart\Micro\Entities\QueryBuilder;

use Dynart\Micro\Entities\EntityManager;
use Dynart\Micro\Entities\EntityManagerException;
use Dynart\Micro\Entities\QueryBuilder;

class MariaQueryBuilder extends QueryBuilder {

    public function columnDefinition(string $columnName, array $columnData): string {
        $parts = [$this->db->escapeName($columnName)];

        $type = $columnData[EntityManager::COLUMN_TYPE];
        $size = array_key_exists(EntityManager::COLUMN_SIZE, $columnData) ? $columnData[EntityManager::COLUMN_SIZE] : 0;
        $fixSize = $this->em->isColumn($columnData, EntityManager::COLUMN_FIX_SIZE);
        $parts[] = $this->sqlType($type, $size, $fixSize);

        if ($this->em->isColumn($columnData, EntityManager::COLUMN_NOT_NULL)) {
            $parts[] = 'not null';
        }

        if ($this->em->isColumn($columnData, EntityManager::COLUMN_AUTO_INCREMENT)) {
            $parts[] = 'auto_increment';
        }

        if (array_key_exists(EntityManager::COLUMN_DEFAULT, $columnData)) {
            $value = $this->sqlDefaultValue($columnData[EntityManager::COLUMN_DEFAULT], $type, $size);
            $parts[] = "default $value";
        }

        return join(' ', $parts);
    }

    public function keyDefinition(string $columnName, array $columnData): string {
        $parts = [];
        if ($this->em->isColumn($columnData, EntityManager::COLUMN_PRIMARY_KEY)) {
            $parts[] = 'primary key ('.$this->db->escapeName($columnName).')';
        }
        if (array_key_exists(EntityManager::COLUMN_FOREIGN_KEY, $columnData)) {
            if (!is_array($columnData[EntityManager::COLUMN_FOREIGN_KEY])) {
                throw new EntityManagerException("Foreign key definition must be an array: ".$columnName);
            }
            if (count($columnData[EntityManager::COLUMN_FOREIGN_KEY]) != 2) {
                throw new EntityManagerException("Foreign key definition array size must be 2: ".$columnName);
            }
            list($foreignClassName, $foreignColumnName) = $columnData[EntityManager::COLUMN_FOREIGN_KEY];
            $part = 'foreign key '.$this->db->escapeName($columnName)
                .' references '.$this->db->escapeName($this->em->createTableNameByClass($foreignClassName))
                .' ('.$this->db->escapeName($foreignColumnName).')';
            if (array_key_exists(EntityManager::COLUMN_ON_DELETE, $columnData)) {
                $part .= ' on delete '.$this->sqlAction($columnData[EntityManager::COLUMN_ON_DELETE]);
            }
            if (array_key_exists(EntityManager::COLUMN_ON_UPDATE, $columnData)) {
                $part .= ' on delete '.$this->sqlAction($columnData[EntityManager::COLUMN_ON_UPDATE]);
            }
            $parts[] = $part;
        }
        return join(",\n", $parts);
    }

    protected function sqlType(string $type, int $size, bool $fixSize) {
        switch ($type) {
            case EntityManager::TYPE_INT:
                return $size ? "int($size)" : 'int';
            case EntityManager::TYPE_STRING:
                if ($size) {
                    return $fixSize ? "char($size)" : "varchar($size)";
                } else {
                    return 'longtext';
                }
            case EntityManager::TYPE_BOOL:
                return 'tinyint(1)';
            case EntityManager::TYPE_DATE:
                return 'date';
            case EntityManager::TYPE_TIME:
                return 'time';
            case EntityManager::TYPE_DATETIME:
                return 'datetime';
            case EntityManager::TYPE_BLOB:
                return 'blob';
            default:
                throw new EntityManagerException("Unknown type: ".$type);
        }
    }

    protected function sqlAction(string $action) {
        switch ($action) {
            case EntityManager::ACTION_CASCADE:
                return 'cascade';
            case EntityManager::ACTION_SET_NULL:
                return 'set null';
            default:
                throw new EntityManagerException('Unknown action: '.$action);
        }
    }

    protected function sqlDefaultValue($value, $type, $size) {
        $isLongText = $type == EntityManager::TYPE_STRING && !$size;
        $isBlob = $type == EntityManager::TYPE_BLOB;
        if ($isLongText || $isBlob) {
            throw new EntityManagerException("Text and blob types can't have default value");
        }
        $isDate = in_array($type, [EntityManager::TYPE_DATE, EntityManager::TYPE_TIME, EntityManager::TYPE_DATETIME]);
        if ($value === null) {
            $value = 'null';
        } else if ($type == EntityManager::TYPE_STRING) {
            $value = '"' . str_replace('"', '\"', $value) . '"';
        } else if ($isDate && $value == EntityManager::DEFAULT_NOW) {
            $value = 'now()';
        } else if ($type == EntityManager::TYPE_BOOL) {
            $value = $value ? '1' : '0';
        }
        return $value;
    }
}