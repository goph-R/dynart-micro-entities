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

    public function isAuditTableExist(string $className): bool {
        $result = $this->db->fetchOne($this->queryBuilder->isTableExist(':dbName', ':tableName'), [
            ':dbName'    => $this->db->configValue('name'),
            ':tableName' => $this->em->tableNameByClass($className).EntityManager::AUDIT_TABLE_SUFFIX
        ]);
        return (bool)$result;
    }

    public function createTable(string $className, bool $ifNotExists = false): void {
        $sql = $this->queryBuilder->createTable($className, $ifNotExists);
        $this->db->query($sql);
    }

    /**
     * Creates the audit mirror table of an entity
     */
    public function createAuditTable(string $className, bool $ifNotExists = false): void {
        $sql = $this->queryBuilder->createAuditTable($className, $ifNotExists);
        $this->db->query($sql);
    }

    /**
     * Creates the table of an entity and, when it is auditable, its audit mirror as well
     */
    public function createTableWithAudit(string $className, bool $ifNotExists = false): void {
        $this->createTable($className, $ifNotExists);
        if ($this->em->isAuditable($className)) {
            $this->createAuditTable($className, $ifNotExists);
        }
    }

    public function dropTable(string $className, bool $ifExists = true): void {
        $this->db->query($this->queryBuilder->dropTableByClass($className, $ifExists));
    }

    public function dropAuditTable(string $className, bool $ifExists = true): void {
        $this->db->query($this->queryBuilder->dropAuditTableByClass($className, $ifExists));
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
