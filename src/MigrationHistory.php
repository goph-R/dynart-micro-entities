<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Entities\Attribute\Column;

/**
 * The record of one applied migration
 */
class MigrationHistory extends Entity {

    protected static string $eventName = 'migration_history';

    #[Column(type: Column::TYPE_STRING, size: 191, primaryKey: true, notNull: true)]
    public string $version = '';

    #[Column(type: Column::TYPE_DATETIME, notNull: true)]
    public ?string $applied_at = null;
}
