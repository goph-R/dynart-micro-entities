<?php

namespace Dynart\Micro\Entities;

/**
 * One forward step of the database schema
 *
 * Migrations are instantiated through the DI container, so a migration can ask for the
 * `QueryExecutor`, the `EntityManager` or any service it needs in its constructor.
 *
 * There is no `down()`. Rolling a schema back is rarely what actually happens in production and
 * a half correct implementation is worse than none, so a mistake is corrected by a new migration.
 */
interface MigrationInterface {

    /**
     * The identifier of this migration, unique and sortable
     *
     * The runner applies pending migrations in the ascending order of this value, so a
     * timestamp-like prefix is the usual choice: `2026_08_04_001_create_content`.
     */
    public function version(): string;

    /**
     * Applies the change
     */
    public function up(): void;
}
