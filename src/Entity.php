<?php

namespace Dynart\Micro\Entities;

/**
 * An abstract class for entities
 *
 * @package Dynart\Micro\Entities
 */
abstract class Entity {

    const EVENT_BEFORE_SAVE = 'before_save';
    const EVENT_AFTER_SAVE = 'after_save';

    private $__isNew = true;

    public function isNew(): bool {
        return $this->__isNew;
    }

    public function setNew(bool $value): void {
        $this->__isNew = $value;
    }

    public function beforeSaveEvent(): string {
        return get_class($this).':'.self::EVENT_BEFORE_SAVE;
    }

    public function afterSaveEvent(): string {
        return get_class($this).':'.self::EVENT_AFTER_SAVE;
    }
}