<?php

declare(strict_types=1);

namespace FSM\Core\ValueObject;

use FSM\Core\Exception\InvalidAutomatonException;

final class TransitionFunction
{
    /** @var array<string, State> Optimized lookup table */
    private array $table = [];
    
    public function __construct()
    {
    }
    
    public function define(State $fromState, Symbol $symbol, State $toState): self
    {
        $key = $this->makeKey($fromState, $symbol);
        $this->table[$key] = $toState;
        return $this;
    }
    
    public function apply(State $state, Symbol $symbol): ?State
    {
        $key = $this->makeKey($state, $symbol);
        return $this->table[$key] ?? null;
    }
    
    public function validate(StateSet $states, Alphabet $alphabet): void
    {
        foreach ($this->table as $toState) {
            if (!$states->contains($toState)) {
                throw new InvalidAutomatonException(
                    "Transition target state '{$toState}' not in state set"
                );
            }
        }
        
        $complete = true;
        $missing = [];
        
        foreach ($states as $state) {
            foreach ($alphabet as $symbol) {
                if ($this->apply($state, $symbol) === null) {
                    $complete = false;
                    $missing[] = "δ({$state}, {$symbol})";
                }
            }
        }
        
        if (!$complete && count($missing) > 0) {
            trigger_error(
                'Transition function is partial. Missing: ' . implode(', ', array_slice($missing, 0, 5)),
                E_USER_NOTICE
            );
        }
    }
    
    private function makeKey(State $state, Symbol $symbol): string
    {
        return "{$state}:{$symbol}";
    }
    
    public function toArray(): array
    {
        $result = [];
        foreach ($this->table as $key => $toState) {
            [$fromState, $symbol] = explode(':', $key);
            $result[] = [
                'from' => $fromState,
                'input' => $symbol,
                'to' => (string)$toState
            ];
        }
        return $result;
    }
}