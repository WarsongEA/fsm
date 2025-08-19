<?php

declare(strict_types=1);

namespace FSM\Core\ValueObject;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

final class StateSet implements Countable, IteratorAggregate
{
    /** @var array<string, State> */
    private array $states = [];
    
    public function __construct(State ...$states)
    {
        foreach ($states as $state) {
            $this->states[(string)$state] = $state;
        }
    }
    
    public function contains(State $state): bool
    {
        return isset($this->states[(string)$state]);
    }
    
    public function containsAll(StateSet $other): bool
    {
        foreach ($other as $state) {
            if (!$this->contains($state)) {
                return false;
            }
        }
        return true;
    }
    
    public function add(State $state): self
    {
        $new = clone $this;
        $new->states[(string)$state] = $state;
        return $new;
    }
    
    public function remove(State $state): self
    {
        $new = clone $this;
        unset($new->states[(string)$state]);
        return $new;
    }
    
    public function union(StateSet $other): self
    {
        $new = clone $this;
        foreach ($other as $state) {
            $new->states[(string)$state] = $state;
        }
        return $new;
    }
    
    public function intersection(StateSet $other): self
    {
        $new = new self();
        foreach ($this->states as $state) {
            if ($other->contains($state)) {
                $new->states[(string)$state] = $state;
            }
        }
        return $new;
    }
    
    public function count(): int
    {
        return count($this->states);
    }
    
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_values($this->states));
    }
    
    public function toArray(): array
    {
        return array_map(fn($s) => (string)$s, array_values($this->states));
    }
}