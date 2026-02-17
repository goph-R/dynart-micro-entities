<?php

namespace Dynart\Micro\Entities\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column {

    const TYPE_INT = 'int';
    const TYPE_LONG = 'long';
    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
    const TYPE_NUMERIC = 'numeric';
    const TYPE_STRING = 'string';
    const TYPE_BOOL = 'bool';
    const TYPE_DATE = 'date';
    const TYPE_TIME = 'time';
    const TYPE_DATETIME = 'datetime';
    const TYPE_BLOB = 'blob';

    const ACTION_CASCADE = 'cascade';
    const ACTION_SET_NULL = 'set_null';

    const NOW = 'now';

    public function __construct(
        public string $type,
        public int|array $size = 0,
        public bool $fixSize = false,
        public bool $notNull = false,
        public bool $autoIncrement = false,
        public bool $primaryKey = false,
        public mixed $default = null,
        public ?array $foreignKey = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {}
}
