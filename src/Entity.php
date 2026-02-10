<?php

namespace Dynart\Micro\Entities;

abstract class Entity {

    const EVENT_BEFORE_SAVE = 'before_save';
    const EVENT_AFTER_SAVE = 'after_save';

    private bool $__isNew = true;
    private array $__snapshot = [];
    private bool $__hasSnapshot = false;

    public function isNew(): bool {
        return $this->__isNew;
    }

    public function setNew(bool $value): void {
        $this->__isNew = $value;
    }

    public function takeSnapshot(array $data): void {
        $this->__snapshot = $data;
        $this->__hasSnapshot = true;
    }

    public function getDirtyFields(array $currentData): array {
        if (!$this->__hasSnapshot) {
            return $currentData;
        }
        $dirty = [];
        foreach ($currentData as $key => $value) {
            if (!array_key_exists($key, $this->__snapshot) || $value !== $this->__snapshot[$key]) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(array $currentData): bool {
        return $this->getDirtyFields($currentData) !== [];
    }

    public function clearSnapshot(): void {
        $this->__snapshot = [];
        $this->__hasSnapshot = false;
    }

    public function beforeSaveEvent(): string {
        return get_class($this).'.'.self::EVENT_BEFORE_SAVE;
    }

    public function afterSaveEvent(): string {
        return get_class($this).'.'.self::EVENT_AFTER_SAVE;
    }
}
