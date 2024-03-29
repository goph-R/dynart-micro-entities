<?php

namespace Dynart\Micro\Entities;

use Dynart\Micro\Annotation;
use Dynart\Micro\MicroException;

class ColumnAnnotation implements Annotation {

    /** @var EntityManager */
    protected $entityManager;

    public function __construct(EntityManager $entityManager) {
        $this->entityManager = $entityManager;
    }

    public function name(): string {
        return 'column';
    }

    public function regex(): string {
        return '(.*)';
    }

    public function types(): array {
        return [Annotation::TYPE_PROPERTY];
    }

    public function process(string $type, string $className, $subject, string $comment, array $matches): void {
        if ($matches) {
            $this->entityManager->addColumn($className, $subject->getName(), json_decode($matches[1], true));
        } else {
            throw new MicroException("Invalid column annotation in comment: $comment");
        }
    }
}