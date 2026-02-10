<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Micro;

class Query {

    const INNER_JOIN = 'inner';
    const LEFT_JOIN = 'left';
    const RIGHT_JOIN = 'right';
    const OUTER_JOIN = 'full outer';

    protected string|Query $from;
    protected array $variables = [];
    protected array $fields = [];
    protected array $joins = [];
    protected array $conditions = [];
    protected array $groups = [];
    protected array $orders = [];
    protected int $offset = -1;
    protected int $max = -1;

    public function __construct(string|Query $from) {
        $this->from = $from;
    }

    public function from(): string|Query {
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

    public function addInnerJoin(string|array $from, string $condition, array $variables = []): void {
        $this->addJoin(self::INNER_JOIN, $from, $condition, $variables);
    }

    public function addJoin(string $type, string|array $from, string $condition, array $variables = []): void {
        $this->joins[] = [$type, $from, $condition];
        $this->addVariables($variables);
    }

    public function joins(): array {
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

    public function setLimit(int $offset, int $max): void {
        $this->offset = $offset;
        $this->max = $max;
    }

    public function offset(): int {
        return $this->offset;
    }

    public function max(): int {
        return $this->max;
    }

    private function shouldSelectAllFields(): bool {
        return empty($this->fields) && is_string($this->from);
    }

    private function allFields(): array {
        return array_keys(Micro::get(EntityManager::class)->tableColumns($this->from));
    }
}
