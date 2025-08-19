<?php

declare(strict_types=1);

namespace FSM\Core\ValueObject;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;
use InvalidArgumentException;

final class Alphabet implements Countable, IteratorAggregate
{
    /** @var array<string, Symbol> */
    private array $symbols = [];
    
    public function __construct(Symbol ...$symbols)
    {
        if (empty($symbols)) {
            throw new InvalidArgumentException('Alphabet must contain at least one symbol');
        }
        
        foreach ($symbols as $symbol) {
            $this->symbols[(string)$symbol] = $symbol;
        }
    }
    
    public function contains(Symbol $symbol): bool
    {
        return isset($this->symbols[(string)$symbol]);
    }
    
    public function count(): int
    {
        return count($this->symbols);
    }
    
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->symbols));
    }
    
    public function toArray(): array
    {
        return array_map(fn($s) => (string)$s, array_values($this->symbols));
    }
}