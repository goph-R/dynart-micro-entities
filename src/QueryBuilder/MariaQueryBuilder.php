<?php

namespace Dynart\Micro\Entities\QueryBuilder;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\EntityManagerException;
use Dynart\Micro\Entities\QueryBuilder;

class MariaQueryBuilder extends QueryBuilder {

    const SIMPLE_TYPE_MAP = [
        Column::TYPE_LONG     => 'bigint',
        Column::TYPE_INT      => 'int',
        Column::TYPE_FLOAT    => 'float',
        Column::TYPE_DOUBLE   => 'double',
        Column::TYPE_BOOL     => 'tinyint(1)',
        Column::TYPE_DATE     => 'date',
        Column::TYPE_TIME     => 'time',
        Column::TYPE_DATETIME => 'datetime',
        Column::TYPE_BLOB     => 'blob'
    ];

    public function columnDefinition(string $columnName, Column $column): string {
        $parts = [$this->db->escapeName($columnName)];
        $parts[] = $this->sqlType($column->type, $column->size, $column->fixSize);
        if ($column->notNull) {
            $parts[] = 'not null';
        }
        if ($column->autoIncrement) {
            $parts[] = 'auto_increment';
        }
        if ($column->default !== null) {
            $parts[] = "default ".$this->sqlDefaultValue($column->default, $column->type, $column->size);
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

    public function foreignKeyDefinition(string $columnName, Column $column): string {
        if ($column->foreignKey === null) {
            return '';
        }
        if (count($column->foreignKey) != 2) {
            throw new EntityManagerException("Foreign key definition array size must be 2: ".$this->currentColumn());
        }
        [$foreignClassName, $foreignColumnName] = $column->foreignKey;
        $result = 'foreign key ('.$this->db->escapeName($columnName).')'
            .' references '.$this->em->safeTableName($foreignClassName)
            .' ('.$this->db->escapeName($foreignColumnName).')';
        if ($column->onDelete !== null) {
            $result .= ' on delete '.$this->sqlAction($column->onDelete);
        }
        if ($column->onUpdate !== null) {
            $result .= ' on update '.$this->sqlAction($column->onUpdate);
        }
        return $result;
    }

    public function isTableExist(string $dbNameParam, string $tableNameParam): string {
        return "select 1 from information_schema.tables where table_schema = $dbNameParam and table_name = $tableNameParam limit 1";
    }

    public function listTables(): string {
        return "show tables";
    }

    public function describeTable(string $className): string {
        return "describe ".$this->em->tableNameByClass($className);
    }

    public function columnsByTableDescription(array $data): array {
        print_r($data);
        return [];
    }

    protected function checkIntSize(mixed $size): void {
        if ($size && !is_int($size)) {
            throw new EntityManagerException("The size has to be an integer! ".$this->currentColumn());
        }
    }

    protected function checkArraySize(mixed $size, int $count): void {
        if (!is_array($size) || count($size) != $count) {
            throw new EntityManagerException("The size array has to have $count elements! ".$this->currentColumn());
        }
    }

    protected function sqlType(string $type, mixed $size, bool $fixSize): string {
        switch ($type) {
            case Column::TYPE_BOOL:
            case Column::TYPE_DATE:
            case Column::TYPE_TIME:
            case Column::TYPE_DATETIME:
            case Column::TYPE_BLOB:
                return self::SIMPLE_TYPE_MAP[$type];

            case Column::TYPE_LONG:
            case Column::TYPE_INT:
            case Column::TYPE_FLOAT:
            case Column::TYPE_DOUBLE:
                $this->checkIntSize($size);
                $mappedType = self::SIMPLE_TYPE_MAP[$type];
                return $size ? "$mappedType($size)" : $mappedType;

            case Column::TYPE_NUMERIC:
                $this->checkArraySize($size, 2);
                return "decimal($size[0], $size[1])";

            case Column::TYPE_STRING:
                if (!$size) {
                    return 'longtext';
                }
                $this->checkIntSize($size);
                return $fixSize ? "char($size)" : "varchar($size)";

            default:
                throw new EntityManagerException("Unknown type '$type': ".$this->currentColumn());
        }
    }

    protected function sqlAction(string $action): string {
        return match ($action) {
            Column::ACTION_CASCADE => 'cascade',
            Column::ACTION_SET_NULL => 'set null',
            default => throw new EntityManagerException("Unknown action '$action': : ".$this->currentColumn()),
        };
    }

    protected function sqlDefaultValue(mixed $value, string $type, int|array $size): string {
        if ($type == Column::TYPE_BLOB || ($type == Column::TYPE_STRING && !$size)) {
            throw new EntityManagerException("Text and blob types can't have a default value: ".$this->currentColumn());
        }
        if ($value === null) {
            return 'null';
        } else if (is_array($value)) {
            if (count($value) != 1) {
                throw new EntityManagerException("Raw default value (array) only can have one element: ".$this->currentColumn());
            }
            return $value[0];
        } else if ($type == Column::TYPE_STRING) {
            return "'".str_replace("'", "\\'", $value)."'";
        } else if ($this->isDateType($type) && $value == Column::NOW) {
            switch ($type) {
                case Column::TYPE_DATETIME:
                    return 'utc_timestamp()';
                case Column::TYPE_DATE:
                    return 'utc_date()';
                case Column::TYPE_TIME:
                    return 'utc_time()';
            }
        } else if ($type == Column::TYPE_BOOL && is_bool($value)) {
            return $value ? '1' : '0';
        }
        return $value;
    }

    protected function isDateType(string $type): bool {
        return in_array($type, [Column::TYPE_DATE, Column::TYPE_TIME, Column::TYPE_DATETIME]);
    }
}
