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

    public function primaryKeyDefinition(string $className): string {
        $result = '';
        $primaryKey = $this->em->primaryKey($className);
        if (!$primaryKey) {
            return $result;
        }
        $result = 'primary key (';
        if (is_array($primaryKey)) {
            $pks = [];
            foreach ($primaryKey as $pk) {
                $pks[] = $this->db->escapeName($pk);
            }
            $result .= join(', ', $pks);
        } else {
            $result .= $this->db->escapeName($primaryKey);
        }
        $result .= ')';
        return $result;
    }

    public function foreignKeyDefinition(string $columnName, array $columnData): string {
        $result = '';
        if (!array_key_exists(EntityManager::COLUMN_FOREIGN_KEY, $columnData)) {
            return $result;
        }
        if (!is_array($columnData[EntityManager::COLUMN_FOREIGN_KEY])) {
            throw new EntityManagerException("Foreign key definition must be an array: ".$columnName);
        }
        if (count($columnData[EntityManager::COLUMN_FOREIGN_KEY]) != 2) {
            throw new EntityManagerException("Foreign key definition array size must be 2: ".$columnName);
        }
        list($foreignClassName, $foreignColumnName) = $columnData[EntityManager::COLUMN_FOREIGN_KEY];
        $result = 'foreign key '.$this->db->escapeName($columnName)
            .' references '.$this->db->escapeName($this->em->tableNameByClass($foreignClassName))
            .' ('.$this->db->escapeName($foreignColumnName).')';
        if (array_key_exists(EntityManager::COLUMN_ON_DELETE, $columnData)) {
            $result .= ' on delete '.$this->sqlAction($columnData[EntityManager::COLUMN_ON_DELETE]);
        }
        if (array_key_exists(EntityManager::COLUMN_ON_UPDATE, $columnData)) {
            $result .= ' on delete '.$this->sqlAction($columnData[EntityManager::COLUMN_ON_UPDATE]);
        }
        return $result;
    }

    protected function sqlType(string $type, $size, bool $fixSize): string {
        switch ($type) {
            case EntityManager::TYPE_LONG:
                if ($size && !is_int($size)) {
                    throw new EntityManagerException("The size has to be an integer!");
                }
                return $size ? "bigint($size)" : 'bigint';
            case EntityManager::TYPE_INT:
                if ($size && !is_int($size)) {
                    throw new EntityManagerException("The size has to be an integer!");
                }
                return $size ? "int($size)" : 'int';
            case EntityManager::TYPE_FLOAT:
                if ($size && !is_int($size)) {
                    throw new EntityManagerException("The size has to be an integer!");
                }
                return $size ? "float($size)" : 'float';
            case EntityManager::TYPE_DOUBLE:
                if ($size && !is_int($size)) {
                    throw new EntityManagerException("The size has to be an integer!");
                }
                return $size ? "double($size)" : 'double';
            case EntityManager::TYPE_DECIMAL:
                if (!is_array($size) || count($size) != 2) {
                    throw new EntityManagerException("The size has to be an array with two element!");
                }
                return "decimal($size[0], $size[1])";
            case EntityManager::TYPE_STRING:
                if ($size) {
                    if ($size && !is_int($size)) {
                        throw new EntityManagerException("The size has to be an integer!");
                    }
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
                throw new EntityManagerException("Unknown type: $type");
        }
    }

    protected function sqlAction(string $action): string {
        switch ($action) {
            case EntityManager::ACTION_CASCADE:
                return 'cascade';
            case EntityManager::ACTION_SET_NULL:
                return 'set null';
            default:
                throw new EntityManagerException("Unknown action: $action");
        }
    }

    protected function sqlDefaultValue($value, string $type, int $size): string {
        $isLongText = $type == EntityManager::TYPE_STRING && !$size;
        $isBlob = $type == EntityManager::TYPE_BLOB;
        if ($isLongText || $isBlob) {
            throw new EntityManagerException("Text and blob types can't have a default value");
        }
        $isDate = in_array($type, [EntityManager::TYPE_DATE, EntityManager::TYPE_TIME, EntityManager::TYPE_DATETIME]);
        if ($value === null) {
            return 'null';
        } else if ($type == EntityManager::TYPE_STRING) {
            return "'".str_replace("'", "\\'", $value)."'";
        } else if ($isDate && $value == EntityManager::DEFAULT_NOW) {
            switch ($type) {
                case EntityManager::TYPE_DATETIME:
                    return 'utc_timestamp()';
                case EntityManager::TYPE_DATE:
                    return 'utc_date()';
                case EntityManager::TYPE_TIME:
                    return 'utc_time()';
            }
        } else if ($type == EntityManager::TYPE_BOOL) {
            return $value ? '1' : '0';
        }
        return $value;
    }
}