<?php

namespace Dynart\Micro\Entities\Attribute;

use Attribute;

/**
 * Declares table level metadata for an Entity subclass
 *
 * Only needed for things that can not be expressed on a single column: composite unique
 * constraints and multi column indexes. Single column constraints belong on the `#[Column]`
 * attribute via its `unique` and `index` parameters.
 *
 * <pre>
 * #[Table(
 *     unique: [['content_id', 'tag_id']],
 *     index:  [['type', 'status', 'published_at']]
 * )]
 * class ContentTag extends Entity { ... }
 * </pre>
 *
 * Both `unique` and `index` accept a list of column name lists. The keys are optional, when
 * given they are used as the constraint name:
 *
 * <pre>
 * #[Table(unique: ['uq_content_tag' => ['content_id', 'tag_id']])]
 * </pre>
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table {

    public function __construct(
        public ?string $name = null,
        public array $unique = [],
        public array $index = [],
    ) {}
}
