<?php

declare(strict_types=1);

namespace FSM\Examples\ModuloThree;

final class ModuloThreeResult
{
    public function __construct(
        public readonly int $modulo,
        public readonly string $finalState,
        public readonly string $decimalValue,
        public readonly array $transitions,
        public readonly float $executionTimeMs
    ) {
    }
}