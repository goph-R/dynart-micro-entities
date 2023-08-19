<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Micro;

/**
 * Represents an SQL SELECT query
 *
 * @package Dynart\Micro\Entities
 */
class Query {

    const INNER_JOIN = 'inner';
    const LEFT_JOIN = 'left';
    const RIGHT_JOIN = 'right';
    const OUTER_JOIN = 'full outer';

    protected $from = '';
    protected $variables = [];
    protected $fields = [];
    protected $joins = [];
    protected $conditions = [];
    protected $groups = [];
    protected $orders = [];
    protected $page = -1;
    protected $pageSize = -1;

    /**
     * Query constructor.
     * @param string|Query $from The source of the query (Entity::class or a Query instance)
     */
    public function __construct($from) {
        $this->from = $from;
    }

    public function from() {
        return $this->from;
    }

    public function addFields(array $fields): void {
        $this->fields = array_merge($this->fields, $fields);
    }

    public function setFields(array $fields): void {
        $this->fields = $fields;
    }

    public function fields(): array {
        return $this->shouldSelectAllFields() ? $this->allFields() : $this->fields;
    }

    public function addVariables(array $variables): void {
        $this->variables = array_merge($this->variables, $variables);
    }

    public function variables(): array {
        return $this->variables;
    }

    public function addCondition(string $condition, array $variables = []): void {
        $this->conditions[] = $condition;
        $this->addVariables($variables);
    }

    public function conditions(): array {
        return $this->conditions;
    }

    public function addInnerJoin($from, string $condition, array $variables = []): void {
        $this->addJoin(self::INNER_JOIN, $from, $condition, $variables);
    }

    public function addJoin(string $type, $from, string $condition, array $variables = []): void {
        $this->joins[] = [$type, $from, $condition];
        $this->addVariables($variables);
    }

    public function joins() {
        return $this->joins;
    }

    public function addGroupBy(string $name): void {
        $this->groups[] = $name;
    }

    public function groupBy(): array {
        return $this->groups;
    }

    public function addOrderBy(string $name, string $dir = 'asc'): void {
        $this->orders[] = [$name, $dir];
    }

    public function orderBy(): array {
        return $this->orders;
    }

    public function setLimit(int $page, int $pageSize): void {
        $this->page = $page;
        $this->pageSize = $pageSize;
    }

    public function page(): int {
        return $this->page;
    }

    public function pageSize(): int {
        return $this->pageSize;
    }

    private function shouldSelectAllFields(): bool {
        return empty($this->fields) && is_string($this->from);
    }

    private function allFields(): array {
        return array_keys(Micro::get(EntityManager::class)->tableColumns($this->from));
    }
}