<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Database;

abstract class QueryBuilder {

    const INDENTATION = '  ';

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $entityManager;

    /** @var string */
    protected $currentClassNameForException;

    /** @var string */
    protected $currentColumnNameForException;

    abstract public function columnDefinition(string $columnName, array $columnData): string;
    abstract public function primaryKeyDefinition(string $className): string;
    abstract public function foreignKeyDefinition(string $columnName, array $columnData): string;
    abstract public function isTableExist(string $className, string $dbNameParam, string $tableNameParam): string;

    public function __construct(Database $db, EntityManager $entityManager) {
        $this->db = $db;
        $this->entityManager = $entityManager;
    }

    public function createTable(string $className, bool $ifNotExists = false): string {
        $this->currentClassNameForException = $className;
        $allColumnDef = [];
        $allForeignKeyDef = [];
        foreach ($this->entityManager->tableColumns($className) as $columnName => $columnData) {
            $this->currentColumnNameForException = $columnName;
            $allColumnDef[] = self::INDENTATION.$this->columnDefinition($columnName, $columnData);
            $foreignKeyDef = $this->foreignKeyDefinition($columnName, $columnData);
            if ($foreignKeyDef) {
                $allForeignKeyDef[] = self::INDENTATION.$foreignKeyDef;
            }
        }
        $primaryKeyDef = $this->primaryKeyDefinition($className);
        $safeTableName = $this->db->escapeName($this->entityManager->tableNameByClass($className));
        $result = "create table ";
        if ($ifNotExists) {
            $result .= "if not exists ";
        }
        $result .= "$safeTableName (\n";
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

    protected function currentColumnForException() {
        return $this->currentClassNameForException.'::'.$this->currentColumnNameForException;
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