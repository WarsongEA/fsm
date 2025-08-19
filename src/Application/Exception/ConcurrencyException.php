<?php

declare(strict_types=1);

namespace FSM\Application\Exception;

/**
 * Exception thrown when there's a concurrency conflict
 */
final class ConcurrencyException extends \RuntimeException
{
    public static function versionMismatch(string $id, int $expectedVersion, int $actualVersion): self
    {
        return new self(
            "Version mismatch for FSM '{$id}': expected {$expectedVersion}, got {$actualVersion}"
        );
    }
}