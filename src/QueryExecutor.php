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

    public function createTable(string $className, bool $ifNotExists = false) {
        $this->db->query($this->queryBuilder->createTable($className, $ifNotExists));
    }

    public function listTables(): array {
        return $this->db->fetchColumn($this->queryBuilder->listTables());
    }

    public function findColumns(string $className): array {
        return $this->queryBuilder->columnsByTableDescription(
            $this->db->fetchAll($this->queryBuilder->describeTable($className))
        );
    }

    public function findAll(Query $query, array $fields = []) {
        return $this->db->fetchAll($this->queryBuilder->findAll($query, $fields), $query->variables());
    }

    public function findAllColumn(Query $query, string $column = '') {
        return $this->db->fetchColumn($this->queryBuilder->findAll($query, $column ? [$column] : []), $query->variables());
    }

    public function findAllCount(Query $query) {
        return $this->db->fetchOne($this->queryBuilder->findAllCount($query), $query->variables());
    }
}