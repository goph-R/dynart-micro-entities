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

    /**
     * Is there a table with this exact name?
     *
     * Takes a raw name rather than a class, for a migration that has to look for a table the
     * entities no longer describe - the one they were called before a rename, say.
     */
    public function isTableNameExist(string $tableName): bool {
        $result = $this->db->fetchOne($this->queryBuilder->isTableExist(':dbName', ':tableName'), [
            ':dbName'    => $this->db->configValue('name'),
            ':tableName' => $tableName
        ]);
        return (bool)$result;
    }

    /**
     * Renames a table, by raw names for the same reason
     */
    public function renameTable(string $fromName, string $toName): void {
        $this->db->query($this->queryBuilder->renameTable(
            $this->db->escapeName($fromName),
            $this->db->escapeName($toName)
        ));
    }

    /**
     * Adds a foreign key to an existing table
     *
     * For a column that references an entity introduced after its own table was created.
     */
    /**
     * Adds a column to an existing table, and to its audit mirror when it has one
     *
     * **This is the one to call from a migration.** Adding the column to the source table alone
     * leaves the mirror a column short, and the next audited save of that entity fails on the
     * column count - for every entity of that class, not just the row being written. Doing both
     * from one call is what stops that from being something a migration has to remember.
     */
    public function addColumnWithAudit(string $className, string $columnName): void {
        $this->addColumn($className, $columnName);
        if ($this->em->isAuditable($className)) {
            $this->addAuditColumn($className, $columnName);
        }
    }

    public function addColumn(string $className, string $columnName): void {
        $this->db->query($this->queryBuilder->addColumnByName($className, $columnName));
    }

    public function addAuditColumn(string $className, string $columnName): void {
        $this->db->query($this->queryBuilder->addAuditColumnByName($className, $columnName));
    }

    public function addForeignKey(string $className, string $columnName): void {
        $this->db->query($this->queryBuilder->addForeignKeyByColumn($className, $columnName));
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
