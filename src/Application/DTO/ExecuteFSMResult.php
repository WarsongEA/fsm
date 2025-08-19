<?php

declare(strict_types=1);

namespace FSM\Application\DTO;

/**
 * Result of executing an FSM
 */
final class ExecuteFSMResult
{
    /**
     * @param array<array{from: string, input: string, to: string}> $transitions
     */
    public function __construct(
        public readonly string $finalState,
        public readonly bool $isAccepted,
        public readonly array $transitions,
        public readonly float $executionTimeMs = 0.0
    ) {
    }
}