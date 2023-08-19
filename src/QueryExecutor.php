<?php

namespace Dynart\Micro\Entities;

/**
 * Executes an SQL query
 *
 * @package Dynart\Micro\Entities
 */
class QueryExecutor {

    /** @var QueryBuilder */
    protected $queryBuilder;

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $em;

    public function __construct(Database $db, EntityManager $em, QueryBuilder $qb) {
        $this->db = $db;
        $this->em = $em;
        $this->queryBuilder = $qb;
    }

    public function isTableExist(string $className): bool {
        $result = $this->db->fetchOne($this->queryBuilder->isTableExist(':dbName', ':tableName'), [
            ':dbName'    => $this->db->configValue('name'),
            ':tableName' => $this->em->tableNameByClass($className)
        ]);
        return $result ? true : false;
    }

    public function createTable(string $className, bool $ifNotExists = false): void {
        $sql = $this->queryBuilder->createTable($className, $ifNotExists);
        $this->db->query($sql);
    }

    public function listTables(): array {
        $sql = $this->queryBuilder->listTables();
        return $this->db->fetchColumn($sql);
    }

    public function findColumns(string $className): array {
        $sql = $this->queryBuilder->describeTable($className);
        return $this->queryBuilder->columnsByTableDescription($this->db->fetchAll($sql));
    }

    public function findAll(Query $query, array $fields = []) {
        $sql = $this->queryBuilder->findAll($query, $fields);
        return $this->db->fetchAll($sql, $query->variables());
    }

    public function findAllColumn(Query $query, string $column = '') {
        $fields = $column ? [$column] : [];
        $sql = $this->queryBuilder->findAll($query, $fields);
        return $this->db->fetchColumn($sql, $query->variables());
    }

    public function findAllCount(Query $query) {
        $sql = $this->queryBuilder->findAllCount($query);
        return $this->db->fetchOne($sql, $query->variables());
    }
}