<?php

declare(strict_types=1);

namespace FSM\Application\DTO;

use FSM\Core\Model\FSMMetadata;

/**
 * Result of creating an FSM
 */
final class CreateFSMResult
{
    public function __construct(
        public readonly string $fsmId,
        public readonly FSMMetadata $metadata
    ) {
    }
}