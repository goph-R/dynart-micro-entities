<?php

namespace Dynart\Micro\Entities\AttributeHandler;

use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\Entities\Attribute\Auditable;
use Dynart\Micro\Entities\EntityManager;

class AuditableAttributeHandler implements AttributeHandlerInterface {

    public function __construct(private EntityManager $entityManager) {}

    public function attributeClass(): string {
        return Auditable::class;
    }

    public function targets(): array {
        return [AttributeHandlerInterface::TARGET_CLASS];
    }

    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->entityManager->setAuditable($className);
    }
}
