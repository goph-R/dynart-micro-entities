<?php

namespace Dynart\Micro\Entities\AttributeHandler;

use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\Entities\Attribute\Column;
use Dynart\Micro\Entities\EntityManager;

class ColumnAttributeHandler implements AttributeHandlerInterface {

    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function attributeClass(): string {
        return Column::class;
    }

    public function targets(): array {
        return [AttributeHandlerInterface::TARGET_PROPERTY];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        /** @var Column $attribute */
        $this->entityManager->addColumn($className, $subject->getName(), $attribute);
    }
}
