<?php

namespace Dynart\Micro\Entities;

/**
 * Executes an SQL query
 *
 * @package Dynart\Micro\Entities
 */
class QueryExecutor {

    /** @var QueryBuilder */
    protected $qb;

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $em;

    public function __construct(Database $db, EntityManager $em, QueryBuilder $qb) {
        $this->db = $db;
        $this->em = $em;
        $this->qb = $qb;
    }

    public function isTableExist(string $className): bool {
        $result = $this->db->fetchOne($this->qb->isTableExist(':dbName', ':tableName'), [
            ':dbName'    => $this->db->configValue('name'),
            ':tableName' => $this->em->tableNameByClass($className)
        ]);
        return $result ? true : false;
    }

    public function createTable(string $className, bool $ifNotExists = false) {
        $this->db->query($this->qb->createTable($className, $ifNotExists));
    }

    public function listTables(): array {
        return $this->db->fetchColumn($this->qb->listTables());
    }

    public function findColumns(string $className): array {
        return $this->qb->columnsByTableDescription(
            $this->db->fetchAll($this->qb->describeTable($className))
        );
    }

    public function findAll(Query $query, array $fields = []) {
        return $this->db->fetchAll($this->qb->findAll($query, $fields), $query->variables());
    }

    public function findAllCount(Query $query) {
        return $this->db->fetchOne($this->qb->findAllCount($query), $query->variables());
    }
}