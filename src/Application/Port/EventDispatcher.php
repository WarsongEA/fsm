<?php

declare(strict_types=1);

namespace FSM\Application\Port;

/**
 * Event dispatcher interface for domain events
 */
interface EventDispatcher
{
    public function dispatch(object $event): void;
}