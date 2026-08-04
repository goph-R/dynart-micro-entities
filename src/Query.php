<?php

namespace Dynart\Micro\Entities;

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
        return $this->fields;
    }

    /**
     * Adds bound variables to the query
     *
     * Binding the same name twice to the same value is allowed, because writing
     * `(a = :id) or (b = :id)` as two conditions is a normal thing to do. Binding it to a
     * *different* value is refused: the SQL keeps both occurrences of the placeholder, so the
     * last write would silently win and the other condition would filter on the wrong value.
     * That never happens while one author owns a query, and becomes a matter of time once
     * plugins attach conditions to queries they did not write.
     *
     * @throws EntityManagerException if a name is already bound to a different value
     */
    public function addVariables(array $variables): void {
        foreach ($variables as $name => $value) {
            if (array_key_exists($name, $this->variables) && $this->variables[$name] !== $value) {
                throw new EntityManagerException(
                    "The query variable '$name' is already bound to a different value."
                    ." Use nextParamName() to get a name that is free."
                );
            }
            $this->variables[$name] = $value;
        }
    }

    /**
     * Returns with a bound variable name that is not in use yet
     *
     * For contributors that do not know what else is on the query - a plugin adding a condition
     * to a query somebody else built:
     *
     * <pre>
     * $name = $query->nextParamName('author');   // ':author_0'
     * $query->addCondition("`author_id` = $name", [$name => 12]);
     * </pre>
     */
    public function nextParamName(string $base): string {
        $base = ltrim($base, ':');
        $index = 0;
        while (array_key_exists(':'.$base.'_'.$index, $this->variables)) {
            $index++;
        }
        return ':'.$base.'_'.$index;
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

}
