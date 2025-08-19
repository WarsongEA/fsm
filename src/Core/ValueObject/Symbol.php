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
        if (empty($value)) {
            throw new InvalidArgumentException('Symbol cannot be empty');
        }
        // Allow multi-character symbols for complex automata
        if (strlen($value) > 10) {
            throw new InvalidArgumentException('Symbol cannot exceed 10 characters');
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