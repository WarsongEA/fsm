<?php

declare(strict_types=1);

namespace FSM\Application\Query;

/**
 * Query to get FSM state
 */
final class GetFSMStateQuery
{
    public function __construct(
        public readonly string $fsmId,
        public readonly int $historyLimit = 0
    ) {
    }
}