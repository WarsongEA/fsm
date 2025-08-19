<?php

declare(strict_types=1);

namespace FSM\Application\DTO;

use FSM\Core\Model\FSMMetadata;

/**
 * Result of getting FSM state
 */
final class GetFSMStateResult
{
    public function __construct(
        public readonly string $currentState,
        public readonly bool $isFinalState,
        public readonly array $history,
        public readonly FSMMetadata $metadata
    ) {
    }
}