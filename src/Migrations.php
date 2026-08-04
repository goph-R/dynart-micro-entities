<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Micro;

/**
 * Runs the pending schema migrations in order
 *
 * Migrations are registered by class name and applied in the ascending order of their
 * `version()`. Every applied version is recorded in the migration history table, so running the
 * migrations again is a no-op.
 *
 * <pre>
 * $migrations = Micro::get(Migrations::class);
 * $migrations->add(CreateContentTables::class);
 * $migrations->add(AddSlugIndex::class);
 * $applied = $migrations->run(); // ['2026_08_04_001_create_content', ...]
 * </pre>
 *
 * The registry is open on purpose: a plugin adds its own migrations to the same runner, and its
 * versions interleave with the application's by sort order.
 */
class Migrations {

    /** @var string[] */
    protected array $migrationClasses = [];

    protected ?array $appliedVersions = null;

    public function __construct(
        protected EntityManager $em,
        protected Database $db,
        protected QueryExecutor $queryExecutor,
    ) {}

    public function postConstruct(): void {
        $this->em->registerEntity(MigrationHistory::class);
    }

    /**
     * Registers a migration class
     *
     * Also registers it in the DI container, because the runner resolves migrations through it -
     * the same thing `AbstractApp::addMiddleware()` does for middlewares.
     *
     * @throws EntityManagerException if the class does not implement MigrationInterface
     */
    public function add(string $className): void {
        if (!is_subclass_of($className, MigrationInterface::class)) {
            throw new EntityManagerException("$className doesn't implement the MigrationInterface");
        }
        if (in_array($className, $this->migrationClasses)) {
            return;
        }
        if (!Micro::hasInterface($className)) {
            Micro::add($className);
        }
        $this->migrationClasses[] = $className;
    }

    /**
     * @return string[] The registered migration class names
     */
    public function migrationClasses(): array {
        return $this->migrationClasses;
    }

    /**
     * Creates the migration history table when it does not exist yet
     */
    public function install(): void {
        if (!$this->queryExecutor->isTableExist(MigrationHistory::class)) {
            $this->queryExecutor->createTable(MigrationHistory::class, true);
            $this->appliedVersions = [];
        }
    }

    public function isInstalled(): bool {
        return $this->queryExecutor->isTableExist(MigrationHistory::class);
    }

    /**
     * @return string[] The versions that were already applied, in ascending order
     */
    public function appliedVersions(): array {
        if ($this->appliedVersions === null) {
            if (!$this->isInstalled()) {
                return [];
            }
            $sql = 'select `version` from '.$this->em->safeTableName(MigrationHistory::class).' order by `version` asc';
            $this->appliedVersions = $this->db->fetchColumn($sql);
        }
        return $this->appliedVersions;
    }

    /**
     * @return MigrationInterface[] The registered migrations that were not applied yet, in order
     */
    public function pending(): array {
        $applied = $this->appliedVersions();
        $result = [];
        foreach ($this->sortedMigrations() as $migration) {
            if (!in_array($migration->version(), $applied)) {
                $result[] = $migration;
            }
        }
        return $result;
    }

    /**
     * Applies every pending migration in order
     *
     * Each migration is recorded as soon as it succeeds, so an exception half way through leaves
     * the already applied ones recorded and the run can be repeated after the cause is fixed.
     * DDL statements commit implicitly in MariaDB, so wrapping the whole run in one transaction
     * would not roll the schema back anyway.
     *
     * @return string[] The versions that were applied by this call
     */
    public function run(): array {
        $this->install();
        $result = [];
        foreach ($this->pending() as $migration) {
            $migration->up();
            $this->recordApplied($migration->version());
            $result[] = $migration->version();
        }
        return $result;
    }

    /**
     * Writes one version into the migration history
     */
    public function recordApplied(string $version): void {
        $history = new MigrationHistory();
        $history->version = $version;
        $history->applied_at = gmdate('Y-m-d H:i:s');
        $this->em->save($history);
        if ($this->appliedVersions !== null) {
            $this->appliedVersions[] = $version;
            sort($this->appliedVersions);
        }
    }

    /**
     * @return MigrationInterface[] Every registered migration ordered by version
     */
    protected function sortedMigrations(): array {
        $result = [];
        foreach ($this->migrationClasses as $className) {
            $result[] = Micro::get($className);
        }
        usort($result, fn($a, $b) => strcmp($a->version(), $b->version()));
        return $result;
    }
}
