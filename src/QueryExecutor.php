<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Database;

class QueryExecutor {

    /** @var QueryBuilder */
    protected $queryBuilder;

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $entityManager;

    public function __construct(Database $db, EntityManager $entityManager, QueryBuilder $queryBuilder) {
        $this->db = $db;
        $this->entityManager = $entityManager;
        $this->queryBuilder = $queryBuilder;
    }

    public function isTableExists(string $className): bool {
        $result = $this->db->fetchOne($this->queryBuilder->isTableExist($className, ':dbName', ':tableName'), [
            ':dbName'    => $this->db->configValue('name'),
            ':tableName' => $this->entityManager->tableNameByClass($className)
        ]);
        return $result ? true : false;
    }

    public function createTable(string $className, bool $ifNotExists = false) {
        $this->db->query($this->queryBuilder->createTable($className, $ifNotExists));
    }

}