<?php

declare(strict_types=1);

namespace FSM\Application\Exception;

/**
 * Exception thrown when FSM is not found
 */
final class FSMNotFoundException extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("FSM with ID '{$id}' not found");
    }
}