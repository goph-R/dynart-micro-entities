<?php

namespace Dynart\Micro\Entities\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column {

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
