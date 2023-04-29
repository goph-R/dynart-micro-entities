<?php

namespace Dynart\Micro\Entities;

class Entity {

    private $__isNew = true;

    public function isNew(): bool {
        return $this->__isNew;
    }

    public function setNew(bool $value) {
        $this->__isNew = $value;
    }
}