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
        $columnData = [EntityManager::COLUMN_TYPE => $attribute->type];

        if ($attribute->size !== 0) {
            $columnData[EntityManager::COLUMN_SIZE] = $attribute->size;
        }
        if ($attribute->fixSize) {
            $columnData[EntityManager::COLUMN_FIX_SIZE] = true;
        }
        if ($attribute->notNull) {
            $columnData[EntityManager::COLUMN_NOT_NULL] = true;
        }
        if ($attribute->autoIncrement) {
            $columnData[EntityManager::COLUMN_AUTO_INCREMENT] = true;
        }
        if ($attribute->primaryKey) {
            $columnData[EntityManager::COLUMN_PRIMARY_KEY] = true;
        }
        if ($attribute->default !== null) {
            $columnData[EntityManager::COLUMN_DEFAULT] = $attribute->default;
        }
        if ($attribute->foreignKey !== null) {
            $columnData[EntityManager::COLUMN_FOREIGN_KEY] = $attribute->foreignKey;
        }
        if ($attribute->onDelete !== null) {
            $columnData[EntityManager::COLUMN_ON_DELETE] = $attribute->onDelete;
        }
        if ($attribute->onUpdate !== null) {
            $columnData[EntityManager::COLUMN_ON_UPDATE] = $attribute->onUpdate;
        }

        $this->entityManager->addColumn($className, $subject->getName(), $columnData);
    }
}
