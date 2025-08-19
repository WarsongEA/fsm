<?php

declare(strict_types=1);

namespace FSM\Application\Command;

/**
 * Command to create a new FSM instance
 */
final class CreateFSMCommand
{
    /**
     * @param array<string> $states
     * @param array<string> $alphabet
     * @param array<string> $finalStates
     * @param array<string, string> $transitions Map of "state:symbol" => "nextState"
     */
    public function __construct(
        public readonly array $states,
        public readonly array $alphabet,
        public readonly string $initialState,
        public readonly array $finalStates,
        public readonly array $transitions,
        public readonly ?string $name = null,
        public readonly ?string $description = null
    ) {
    }
}