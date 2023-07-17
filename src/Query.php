<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;

class Query {

    const CONFIG_MAX_PAGE_SIZE = 'entities.query.max_page_size';
    const DEFAULT_MAX_PAGE_SIZE = 1000;

    /** @var Database */
    protected $db;

    /** @var EntityManager */
    protected $entityManager;

    protected $className = '';
    protected $maxPageSize;

    protected $variables = [];

    protected $fields = [];
    protected $orderByFields = [];
    protected $joins = [];
    protected $conditions = [];

    public function __construct(Config $config, Database $db, EntityManager $entityManager, string $className) {
        $this->db = $db;
        $this->entityManager = $entityManager;
        $this->className = $className;
        $this->maxPageSize = $config->get(self::CONFIG_MAX_PAGE_SIZE, self::DEFAULT_MAX_PAGE_SIZE);
    }

    public function addFields(array $fields) {
        $this->fields = array_merge($this->fields, $fields);
    }

    public function setFields(array $fields) {
        $this->fields = $fields;
    }

    public function addVariables(array $variables) {
        $this->variables = array_merge($this->variables, $variables);
    }

    public function addConditions(array $conditions, array $variables = []) {
        $this->conditions = array_merge($this->conditions, $conditions);
        $this->addVariables($variables);
    }

    public function addJoin(string $className, array $conditions, array $variables) {

    }

    public function findAll(array $params = []) {
        $sql = $this->select($this->fields, $params);
        $sql .= $this->where($params);
        $sql .= $this->order($params);
        $sql .= $this->limit($params);
        return $this->db->fetchAll($sql, $this->variables);
    }

    public function findAllCount(array $params = []) {
        $sql = $this->select(['c' => ['count(1)']], $params);
        $sql .= $this->where($params);
        return $this->db->fetchOne($sql, $this->variables);
    }

    protected function select(array $fields, array $params) {
        $select = [];
        $this->orderByFields = [];
        foreach ($fields as $as => $name) {
            $safeName = is_array($name) ? $name[0] : $this->db->escapeName($name);
            if (is_int($as)) {
                $this->orderByFields[] = $name;
                $select[] = $safeName;
            } else {
                $this->orderByFields[] = $as;
                $select[] = $safeName.' as '.$this->db->escapeName($as);
            }
        }
        $sql = 'select '.join(', ', $select).' from '.$this->entityManager->safeTableName($this->className);
        $sql .= $this->joins($params);
        return $sql;
    }

    protected function joins() {
        return '';
    }

    protected function where() {
        if (empty($this->conditions)) {
            return '';
        }
        return '';
    }

    protected function order(array $params) {
        if (!isset($params['order_by']) || !isset($params['order_dir'])) {
            return '';
        }
        $orderBy = $params['order_by'];
        if (!in_array($orderBy, $this->orderByFields)) {
            return '';
        }
        $orderDir = $params['order_dir'] == 'desc' ? 'desc' : 'asc';
        return ' order by '.$this->db->escapeName($orderBy).' '.$orderDir;
    }

    protected function getLimit(array $params) {
        if (!isset($params['page']) || !isset($params['page_size'])) {
            return '';
        }
        $page = (int)$params['page'];
        $pageSize = (int)$params['page_size'];
        if ($page < 0) {
            $page = 0;
        }
        if ($pageSize < 1) {
            $pageSize = 1;
        }
        if ($pageSize > $this->maxPageSize) {
            $pageSize = $this->maxPageSize;
        }
        return ' limit '.($page * $pageSize).', '.$pageSize;
    }
}