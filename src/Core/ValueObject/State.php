<?php

declare(strict_types=1);

namespace FSM\Core\ValueObject;

use Stringable;
use InvalidArgumentException;

final class State implements Stringable
{
    public function __construct(
        private readonly string $name
    ) {
        if (empty($name)) {
            throw new InvalidArgumentException('State name cannot be empty');
        }
    }
    
    public function equals(State $other): bool
    {
        return $this->name === $other->name;
    }
    
    public function __toString(): string
    {
        return $this->name;
    }
}