<?php

declare(strict_types=1);

namespace FSM\Application\Event;

use FSM\Core\Model\FSMInstance;

/**
 * Event dispatched when an FSM is created
 */
final class FSMCreatedEvent
{
    public function __construct(
        public readonly FSMInstance $instance,
        public readonly \DateTimeImmutable $occurredAt
    ) {
    }
    
    public static function fromInstance(FSMInstance $instance): self
    {
        return new self($instance, new \DateTimeImmutable());
    }
}