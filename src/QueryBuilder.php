<?php

namespace Dynart\Micro\Entities;

interface QueryBuilder {

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
        "primaryKey": true | false
        "foreignKey": ["tableName", "columnName"]
        "index": true | false
        "default": *

    columnDefinition($columnName, $columnData) -- creates the column definition for the column:
        !columnName int not null auto_increment
        !columnName varchar(255) not null default
        ...


    keyDefinition($columnName, $columnData) -- creates the keys definition:
        primary key ( !columnName )
        foreign key ( !columnName ) references !foreignKey0 ( !foreignKey1 )
        index(!columnName)

    listTables() "show tables"

    createTable($tableName, $tableData) "create table !tableName ( !allColumnDefinitions !allKeyDefinitions )"


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