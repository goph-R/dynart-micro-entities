<?php

namespace Dynart\Micro\Entities\AttributeHandler;

use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\Entities\Attribute\Table;
use Dynart\Micro\Entities\EntityManager;

class TableAttributeHandler implements AttributeHandlerInterface {

    public function __construct(private EntityManager $entityManager) {}

    public function attributeClass(): string {
        return Table::class;
    }

    public function targets(): array {
        return [AttributeHandlerInterface::TARGET_CLASS];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        /** @var Table $attribute */
        $this->entityManager->addTable($className, $attribute);
    }
}
