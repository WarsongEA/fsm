<?php

declare(strict_types=1);

namespace FSM\Core\Result;

use FSM\Core\ValueObject\State;
use FSM\Core\ValueObject\Symbol;

final class TransitionRecord
{
    public function __construct(
        public readonly State $fromState,
        public readonly Symbol $symbol,
        public readonly State $toState,
        public readonly ?float $timestamp = null
    ) {
    }
}