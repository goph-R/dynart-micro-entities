<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Database;

abstract class QueryBuilder {

    const INDENTATION = '  ';

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $em;

    abstract public function columnDefinition(string $columnName, array $columnData): string;
    abstract public function primaryKeyDefinition(string $className): string;
    abstract public function foreignKeyDefinition(string $columnName, array $columnData): string;

    public function __construct(Database $db, EntityManager $em) {
        $this->db = $db;
        $this->em = $em;
    }

    public function createTable(string $className): string {
        $allColumnDef = [];
        $allForeignKeyDef = [];
        foreach ($this->em->tableColumns($className) as $columnName => $columnData) {
            $allColumnDef[] = self::INDENTATION.$this->columnDefinition($columnName, $columnData);
            $foreignKeyDef = $this->foreignKeyDefinition($columnName, $columnData);
            if ($foreignKeyDef) {
                $allForeignKeyDef[] = self::INDENTATION.$foreignKeyDef;
            }
        }
        $primaryKeyDef = $this->primaryKeyDefinition($className);
        $safeTableName = $this->db->escapeName($this->em->tableNameByClass($className));
        $result = "create table $safeTableName (\n";
        $result .= join(",\n", $allColumnDef);
        if ($primaryKeyDef) {
            $result .= ",\n".self::INDENTATION.$primaryKeyDef;
        }
        if (!empty($allForeignKeyDef)) {
            $result .= ",\n".join(",\n", $allForeignKeyDef);
        }
        $result .= "\n)";
        return $result;
    }
/*
    tableData format:
        "columnName1": columnData1
        "columnName2": columnData2

    columnData format:
        "type": "int" | "float" | "string" | "bool" | "datetime" | "date" | "time" | "blob"
        "size": 100
        "fixSize": true | false
        "notNull": true | false
        "autoIncrement": true | false
        "default": *

        "primaryKey": true | false
        "foreignKey": ["tableName", "columnName"]

    createTable($tableName, $tableData) "create table !tableName ( !allColumnDefinitions !allKeyDefinitions )"

    columnDefinition($columnName, $columnData) -- creates the column definition for the column:
        !columnName int not null auto_increment
        !columnName varchar(255) not null default
        ...


    keyDefinition($columnName, $columnData) -- creates the keys definition:
        primary key ( !columnName )
        foreign key ( !columnName ) references !foreignKey0 ( !foreignKey1 )
        index(!columnName)

    listTables() "show tables"
    dropTable($tableName) "drop table !tableName"

    addColumn($tableName, $columnName, $columnData) "alter table !tableName add column !columnDefinition"
    changeColumn($tableName, $columnName, $columnData) "alter table !tableName change column !columnDefinition"
    dropColumn($tableName, $columnName) "alter table !tableName drop column !columnName"

    addPrimaryKey($tableName, $columnName)
    addForeignKey($tableName, $columnName, $foreignTableName, $foreignColumnName)
    addKey($tableName, $columnName, $unique)
    addIndex($tableName, $columnName, $unique)

    dropPrimaryKey($tableName, $columnName)
    dropForeignKey($tableName, $columnName)
    dropKey($tableName, $columnName)
    dropIndex($tableName, $columnName)

*/

}