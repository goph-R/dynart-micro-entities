<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Config;

abstract class Query {

    const CONFIG_MAX_PAGE_SIZE = 'entities.query.max_page_size';
    const DEFAULT_MAX_PAGE_SIZE = 100;

    /** @var Database */
    protected $db;

    protected $maxPageSize;

    protected $sqlParams = [];
    protected $orderByFields = [];

    protected $fields = [];
    protected $joins = [];
    protected $conditions = [];

    public function __construct(Config $config, Database $db) {
        $this->maxPageSize = $config->get(self::CONFIG_MAX_PAGE_SIZE, self::DEFAULT_MAX_PAGE_SIZE);
        $this->db = $db;
    }

    public function addField(string $name, string $as = '') {
        if ($as) {
            $this->fields[$as] = $name;
        } else {
            $this->fields[] = $name;
        }
    }

    public function findAll(array $params = []) {
        $sql = $this->getSelect($params);
        $sql .= $this->getWhere($params);
        $sql .= $this->getOrder($params);
        $sql .= $this->getLimit($params);
        return $this->db->fetchAll($sql, $this->sqlParams);
    }

    public function findAllCount(array $params = []) {
        $fields = ['c' => ['count(1)']];
        $sql = $this->getSelect($fields, $params);
        $sql .= $this->getWhere($params);
        return $this->db->fetchOne($sql, $this->sqlParams);
    }

    protected function getSelect(array $params) {
        $select = [];
        $this->orderByFields = [];
        foreach ($this->fields as $as => $name) {
            $safeName = is_array($name) ? $name[0] : $this->db->escapeName($name);
            if (is_int($as)) {
                $this->orderByFields[] = $name;
                $select[] = $safeName;
            } else {
                $this->orderByFields[] = $as;
                $select[] = $safeName.' as '.$this->db->escapeName($as);
            }
        }
        $sql = 'select '.join(', ', $select).' from '.$this->safeTableName();
        $sql .= $this->getJoins($params);
        return $sql;
    }

    protected function getJoins(array $params) {
        return '';
    }

    protected function getWhere(array $params) {
        return '';
    }

    protected function getOrder(array $params) {
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
        if ($page < 0) $page = 0;
        if ($pageSize < 1) $pageSize = 1;
        if ($pageSize > 100) $pageSize = $this->maxPageSize;
        return ' limit '.($page * $pageSize).', '.$pageSize;
    }
}