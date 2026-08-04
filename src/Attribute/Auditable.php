<?php

namespace Dynart\Micro\Entities\Attribute;

use Attribute;

/**
 * Marks an Entity subclass for auditing
 *
 * An auditable entity gets a mirror table named after it with an `_aud` suffix. Every insert,
 * update and delete writes a full copy of the row into that table together with the id of the
 * revision it happened in and the kind of change.
 *
 * <pre>
 * #[Auditable]
 * class Content extends Entity { ... }
 * </pre>
 *
 * The mirror table is created by `QueryExecutor::createAuditTable()` and written by
 * `AuditService`, which has to be subscribed to the entity events with `subscribeAll()`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Auditable {

    public function __construct() {}
}
