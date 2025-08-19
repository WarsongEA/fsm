<?php

declare(strict_types=1);

namespace FSM\Core\ValueObject;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

final class InputString implements Countable, IteratorAggregate
{
    /** @var Symbol[] */
    private array $symbols;
    
    public function __construct(string $input)
    {
        $this->symbols = array_map(
            fn($char) => new Symbol($char),
            str_split($input)
        );
    }
    
    public function symbols(): array
    {
        return $this->symbols;
    }
    
    public function count(): int
    {
        return count($this->symbols);
    }
    
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->symbols);
    }
    
    public function __toString(): string
    {
        return implode('', array_map(fn($s) => (string)$s, $this->symbols));
    }
}