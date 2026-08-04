<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\EventServiceInterface;

/**
 * Writes the history of the auditable entities
 *
 * Subscribes to the after save and after delete events of every entity marked with
 * `#[Auditable]` and copies the full row into the `_aud` mirror table, tagged with the id of the
 * current revision and the kind of change.
 *
 * All the changes of one request share a single revision, created lazily on the first write, so
 * an untouched request costs nothing. Call `reset()` between requests in a long running process.
 *
 * The subscriptions can only be made after the attribute processor has run, so `subscribeAll()`
 * is wired to the `app:init_finished` event. An application only has to register the service:
 *
 * <pre>
 * Micro::add(AuditService::class);
 * Micro::get(AuditService::class); // instantiate it so it can subscribe
 * </pre>
 */
class AuditService {

    /** A row was inserted */
    const TYPE_ADD = 'add';

    /** A row was updated */
    const TYPE_MOD = 'mod';

    /** A row was deleted */
    const TYPE_DEL = 'del';

    protected ?int $revisionId = null;
    protected ?string $userId = null;
    protected bool $enabled = true;
    protected array $subscribed = [];

    public function __construct(
        protected EntityManager $em,
        protected Database $db,
        protected EventServiceInterface $events,
    ) {}

    public function postConstruct(): void {
        $this->em->registerEntity(Revision::class);
        $this->events->subscribe('app:init_finished', [$this, 'subscribeAll']);
    }

    /**
     * Subscribes to the events of every entity that is marked auditable
     *
     * Safe to call more than once, an entity is only subscribed once.
     */
    public function subscribeAll(): void {
        foreach ($this->em->auditableClasses() as $className) {
            $this->subscribe($className);
        }
    }

    public function subscribe(string $className): void {
        if (in_array($className, $this->subscribed)) {
            return;
        }
        $this->subscribed[] = $className;
        $this->events->subscribe($className::event(Entity::EVENT_AFTER_SAVE), [$this, 'onAfterSave']);
        $this->events->subscribe($className::event(Entity::EVENT_AFTER_DELETE), [$this, 'onAfterDelete']);
    }

    /**
     * Sets who is making the changes, stored on the revision
     */
    public function setUserId(?string $userId): void {
        $this->userId = $userId;
    }

    public function userId(): ?string {
        return $this->userId;
    }

    /**
     * Turns the writing off, for bulk imports or fixtures where history is not wanted
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function enabled(): bool {
        return $this->enabled;
    }

    /**
     * Forgets the current revision so the next change starts a new one
     */
    public function reset(): void {
        $this->revisionId = null;
    }

    /**
     * Returns with the id of the current revision, creating it on the first call
     */
    public function revisionId(): int {
        if ($this->revisionId === null) {
            $revision = new Revision();
            $revision->created_at = gmdate('Y-m-d H:i:s');
            $revision->user_id = $this->userId;
            $this->em->save($revision);
            $this->revisionId = (int)$revision->id;
        }
        return $this->revisionId;
    }

    public function onAfterSave(Entity $entity, string $operation = EntityManager::OPERATION_NONE): void {
        if ($operation === EntityManager::OPERATION_NONE) {
            return; // nothing was written, so there is nothing to record
        }
        $this->write(
            $entity,
            $operation === EntityManager::OPERATION_INSERT ? self::TYPE_ADD : self::TYPE_MOD
        );
    }

    public function onAfterDelete(Entity $entity): void {
        $this->write($entity, self::TYPE_DEL);
    }

    /**
     * Copies the current state of an entity into its audit mirror table
     *
     * An entity has at most one row per revision, holding its state at the end of that revision.
     * Changing the same entity twice inside one revision therefore overwrites the earlier row
     * rather than adding a second one - which is also what the `(primary key, rev_id)` primary
     * key of the mirror table enforces. Use `reset()` to start a new revision when the individual
     * steps matter.
     */
    protected function write(Entity $entity, string $type): void {
        if (!$this->enabled) {
            return;
        }
        $className = get_class($entity);
        $data = $this->em->fetchDataArray($entity);
        $revisionId = $this->revisionId();
        $data[EntityManager::AUDIT_REVISION_COLUMN] = $revisionId;
        $data[EntityManager::AUDIT_TYPE_COLUMN] = $type;
        $this->deleteFromRevision($className, $data, $revisionId);
        $this->db->insert($this->em->auditTableName($className), $data);
    }

    /**
     * Removes the row this entity may already have in the current revision
     *
     * Done with a delete and an insert rather than an upsert so it stays portable across the
     * database backends.
     */
    protected function deleteFromRevision(string $className, array $data, int $revisionId): void {
        $primaryKeyColumns = $this->em->primaryKeyColumns($className);
        if (empty($primaryKeyColumns)) {
            return; // without a primary key there is nothing to overwrite
        }
        $conditions = [];
        $params = [':auditRevId' => $revisionId];
        foreach ($primaryKeyColumns as $i => $columnName) {
            $conditions[] = $this->db->escapeName($columnName).' = :auditPk'.$i;
            $params[':auditPk'.$i] = $data[$columnName];
        }
        $conditions[] = $this->db->escapeName(EntityManager::AUDIT_REVISION_COLUMN).' = :auditRevId';
        $sql = 'delete from '.$this->em->safeAuditTableName($className).' where '.join(' and ', $conditions);
        $this->db->query($sql, $params, true);
    }
}
