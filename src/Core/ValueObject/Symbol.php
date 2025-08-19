<?php

declare(strict_types=1);

namespace FSM\Core\ValueObject;

use Stringable;
use InvalidArgumentException;

final class Symbol implements Stringable
{
    public function __construct(
        private readonly string $value
    ) {
        if (strlen($value) !== 1) {
            throw new InvalidArgumentException('Symbol must be a single character');
        }
    }
    
    public function equals(Symbol $other): bool
    {
        return $this->value === $other->value;
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
}