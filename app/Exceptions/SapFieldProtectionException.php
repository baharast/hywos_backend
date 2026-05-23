<?php

namespace App\Exceptions;

use RuntimeException;

class SapFieldProtectionException extends RuntimeException
{
    public function __construct(
        public readonly array $lockedFields,
        public readonly string $entityType,
        public readonly string $entityId,
        ?string $message = null
    ) {
        parent::__construct(
            $message ?? 'Cannot update SAP-owned fields locally. Use a controlled correction request.'
        );
    }
}
