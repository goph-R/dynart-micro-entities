<?php

namespace Dynart\Micro\Entities;

class QueryExecutor {

    public function __construct(
        protected Database $db,
        protected EntityManager $em,
        protected QueryBuilder $queryBuilder,
    ) {}

    public function isTableExist(string $className): bool {
        $result = $this->db->fetchOne($this->queryBuilder->isTableExist(':dbName', ':tableName'), [
            ':dbName'    => $this->db->configValue('name'),
            ':tableName' => $this->em->tableNameByClass($className)
        ]);
        return (bool)$result;
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

    public function findAll(Query $query, array $fields = []): array {
        $sql = $this->queryBuilder->findAll($query, $fields);
        return $this->db->fetchAll($sql, $query->variables());
    }

    public function findAllColumn(Query $query, string $column = ''): array {
        $fields = $column ? [$column] : [];
        $sql = $this->queryBuilder->findAll($query, $fields);
        return $this->db->fetchColumn($sql, $query->variables());
    }

    public function findAllCount(Query $query): mixed {
        $sql = $this->queryBuilder->findAllCount($query);
        return $this->db->fetchOne($sql, $query->variables());
    }
}
