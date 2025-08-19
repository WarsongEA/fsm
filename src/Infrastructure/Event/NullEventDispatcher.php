<?php

declare(strict_types=1);

namespace FSM\Infrastructure\Event;

use FSM\Application\Port\EventDispatcher;

/**
 * Null event dispatcher for testing
 */
final class NullEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        // Do nothing - null implementation
    }
}