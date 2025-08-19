<?php

declare(strict_types=1);

namespace FSM\Core\Result;

use FSM\Core\ValueObject\State;

final class ComputationResult
{
    /**
     * @param State $finalState The final state after processing all input
     * @param bool $isAccepted Whether the final state is an accepting state
     * @param TransitionRecord[] $transitions The sequence of transitions taken
     */
    public function __construct(
        public readonly State $finalState,
        public readonly bool $isAccepted,
        public readonly array $transitions
    ) {
    }
    
    public function getPath(): array
    {
        return array_map(fn($t) => [
            'from' => (string)$t->fromState,
            'input' => (string)$t->symbol,
            'to' => (string)$t->toState
        ], $this->transitions);
    }
}