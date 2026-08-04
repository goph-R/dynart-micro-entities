<?php

namespace Dynart\Micro\Entities;

abstract class Entity {

    const EVENT_BEFORE_SAVE = 'before_save';
    const EVENT_AFTER_SAVE = 'after_save';
    const EVENT_BEFORE_DELETE = 'before_delete';
    const EVENT_AFTER_DELETE = 'after_delete';

    /**
     * The prefix of every entity event namespace
     *
     * It separates the ORM level events from the service level events of an application: an
     * entity named Content emits `entity.content:before_save` while a ContentService is free to
     * emit `content:before_delete` without the two colliding.
     */
    const EVENT_NAMESPACE_PREFIX = 'entity';

    /**
     * The short name used in the event namespace of this entity
     *
     * When it is empty, the fully qualified class name is used with the backslashes replaced by
     * dots, which is always unique. Declare it in a subclass for readable event names:
     *
     * <pre>
     * class Content extends Entity {
     *     protected static string $eventName = 'content'; // entity.content:before_save
     * }
     * </pre>
     */
    protected static string $eventName = '';

    private bool $__isNew = true;
    private array $__snapshot = [];
    private bool $__hasSnapshot = false;

    /**
     * Returns with the event namespace of this entity, for example `entity.content`
     */
    public static function eventNamespace(): string {
        return self::EVENT_NAMESPACE_PREFIX.'.'.(static::$eventName
            ?: strtolower(str_replace('\\', '.', static::class)));
    }

    /**
     * Returns with a full event name for this entity, for example `entity.content:before_save`
     */
    public static function event(string $name): string {
        return static::eventNamespace().':'.$name;
    }

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

    public function snapshot(): array {
        return $this->__snapshot;
    }

    public function beforeSaveEvent(): string {
        return static::event(self::EVENT_BEFORE_SAVE);
    }

    public function afterSaveEvent(): string {
        return static::event(self::EVENT_AFTER_SAVE);
    }

    public function beforeDeleteEvent(): string {
        return static::event(self::EVENT_BEFORE_DELETE);
    }

    public function afterDeleteEvent(): string {
        return static::event(self::EVENT_AFTER_DELETE);
    }
}
