<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;

/**
 * Builds an SQL query
 *
 * @package Dynart\Micro\Entities
 */
abstract class QueryBuilder {

    const CONFIG_MAX_LIMIT = 'entities.query_builder.max_limit';
    const DEFAULT_MAX_LIMIT = 1000;

    const INDENTATION = '  ';

    private static $subQueryCounter = 0;

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $em;

    protected $currentClassNameForException;
    protected $currentColumnNameForException;
    protected $maxLimit;

    abstract public function columnDefinition(string $columnName, array $columnData): string;
    abstract public function primaryKeyDefinition(string $className): string;
    abstract public function foreignKeyDefinition(string $columnName, array $columnData): string;
    abstract public function isTableExist(string $dbNameParam, string $tableNameParam): string;
    abstract public function listTables(): string;
    abstract public function describeTable(string $className): string;
    abstract public function columnsByTableDescription(array $data): array;

    public function __construct(Config $config, Database $db, EntityManager $entityManager) {
        $this->db = $db;
        $this->em = $entityManager;
        $this->maxLimit = $config->get(self::CONFIG_MAX_LIMIT, self::DEFAULT_MAX_LIMIT);
    }

    public function createTable(string $className, bool $ifNotExists = false): string {
        $this->currentClassNameForException = $className;
        $allColumnDef = [];
        $allForeignKeyDef = [];
        foreach ($this->em->tableColumns($className) as $columnName => $columnData) {
            $this->currentColumnNameForException = $columnName;
            $allColumnDef[] = self::INDENTATION . $this->columnDefinition($columnName, $columnData);
            $foreignKeyDef = $this->foreignKeyDefinition($columnName, $columnData);
            if ($foreignKeyDef) {
                $allForeignKeyDef[] = self::INDENTATION . $foreignKeyDef;
            }
        }
        $primaryKeyDef = $this->primaryKeyDefinition($className);
        $safeTableName = $this->em->safeTableName($className);
        $result = "create table ";
        if ($ifNotExists) {
            $result .= "if not exists ";
        }
        $result .= "$safeTableName (\n";
        $result .= join(",\n", $allColumnDef);
        if ($primaryKeyDef) {
            $result .= ",\n" . self::INDENTATION . $primaryKeyDef;
        }
        if (!empty($allForeignKeyDef)) {
            $result .= ",\n" . join(",\n", $allForeignKeyDef);
        }
        $result .= "\n)";
        return $result;
    }

    // TODO: public function findAllUnion(array $queries): string

    public function findAll(Query $query, array $fields = []): string {
        $sql = $this->select($query, $fields);
        $sql .= $this->joins($query);
        $sql .= $this->where($query);
        $sql .= $this->groupBy($query);
        $sql .= $this->orderBy($query);
        $sql .= $this->limit($query);
        return $sql;
    }

    public function findAllCount(Query $query): string {
        $sql = $this->select($query, ['c' => ['count(1)']]);
        $sql .= $this->joins($query);
        $sql .= $this->where($query);
        $sql .= $this->groupBy($query);
        return $sql;
    }

    public function fieldNames(array $fields): array {
        $result = [];
        foreach ($fields as $as => $name) {
            $safeName = is_array($name) ? $name[0] : $this->db->escapeName($name);
            if (is_int($as)) {
                $result[] = $safeName;
            } else {
                $result[] = $safeName.' as '.$this->db->escapeName($as);
            }
        }
        return $result;
    }

    protected function select(Query $query, array $fields = []): string {
        $selectFields = empty($fields) ? $query->fields() : $fields;
        $queryFrom = $query->from();
        if (is_subclass_of($queryFrom, Query::class)) {
            /** @var Query $queryFrom */
            self::$subQueryCounter++; // TODO: better solution?
            $from = '('.$this->findAll($queryFrom, []).') S'.self::$subQueryCounter;
        } else {
            $from = $this->em->safeTableName($queryFrom);
        }
        return 'select '.join(', ', $this->fieldNames($selectFields)).' from '.$from;
    }

    protected function joins(Query $query): string {
        $joins = [];
        foreach ($query->joins() as $join) {
            list($type, $from, $condition) = $join;
            $fromStr = is_array($from)
                ? $this->em->safeTableName($from[0]).' as '.$this->db->escapeName($from[1])
                : $this->em->safeTableName($from);
            $joins[] = $type.' join '.$fromStr.' on '.$condition;
        }
        return $joins ? join("\n", $joins) : '';
    }

    protected function where(Query $query): string {
        return empty($query->conditions())
            ? ''
            : ' where ('.join(') and (', $query->conditions()).')';
    }

    protected function groupBy(Query $query): string {
        return empty($query->groupBy()) ? '' : ' group by '.join(', ', $query->groupBy());
    }

    protected function orderBy(Query $query): string {
        $orders = [];
        $fieldNames = array_keys($query->fields());
        foreach ($query->orderBy() as $orderBy) {
            if (in_array($orderBy[0], $fieldNames)) {
                $orders[] = $this->db->escapeName($orderBy[0]).' '.($orderBy[1] == 'desc' ? 'desc' : 'asc');
            }
        }
        return $orders ? ' order by '.join(', ', $orders) : '';
    }

    protected function limit(Query $query): string {
        if ($query->offset() == -1 || $query->max() == -1) {
            return '';
        }
        $offset = $query->offset();
        $max = $query->max();
        if ($offset < 0) {
            $offset = 0;
        }
        if ($max < 1) {
            $max = 1;
        }
        if ($max > $this->maxLimit) {
            $max = $this->maxLimit;
        }
        return ' limit '.$offset.', '.$max;
    }

    protected function currentColumn(): string {
        // TODO: better solution?
        return $this->currentClassNameForException.'::'.$this->currentColumnNameForException;
    }
}