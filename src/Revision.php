<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\Attribute\Table;

/**
 * One unit of change
 *
 * Every audit row points at a revision, so all the rows written by the same request share one.
 * Revisions are kept forever, which is why `created_at` is indexed: history is queried by time
 * far more often than by anything else, and the table only ever grows.
 */
#[Table(name: 'revision')]
class Revision extends Entity {

    protected static string $eventName = 'revision';

    #[Column(type: Column::TYPE_LONG, primaryKey: true, autoIncrement: true, notNull: true)]
    public int $id = 0;

    #[Column(type: Column::TYPE_DATETIME, notNull: true, index: true)]
    public ?string $created_at = null;

    /**
     * Who made the change, as a string so any kind of user id fits
     */
    #[Column(type: Column::TYPE_STRING, size: 64, index: true)]
    public ?string $user_id = null;
}
