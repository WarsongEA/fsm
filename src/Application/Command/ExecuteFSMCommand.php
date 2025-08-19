<?php

declare(strict_types=1);

namespace FSM\Application\Command;

/**
 * Command to execute input on an FSM
 */
final class ExecuteFSMCommand
{
    public function __construct(
        public readonly string $fsmId,
        public readonly string $input,
        public readonly bool $recordHistory = false
    ) {
    }
}