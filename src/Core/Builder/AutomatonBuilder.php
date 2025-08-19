<?php

declare(strict_types=1);

namespace FSM\Core\Builder;

use FSM\Core\Model\FiniteAutomaton;
use FSM\Core\ValueObject\State;
use FSM\Core\ValueObject\StateSet;
use FSM\Core\ValueObject\Symbol;
use FSM\Core\ValueObject\Alphabet;
use FSM\Core\ValueObject\TransitionFunction;
use FSM\Core\Exception\InvalidAutomatonException;

final class AutomatonBuilder
{
    private array $states = [];
    private array $alphabet = [];
    private ?string $initialState = null;
    private array $finalStates = [];
    private array $transitions = [];
    
    public static function create(): self
    {
        return new self();
    }
    
    public function withStates(string ...$states): self
    {
        $this->states = $states;
        return $this;
    }
    
    public function withAlphabet(string ...$symbols): self
    {
        $this->alphabet = $symbols;
        return $this;
    }
    
    public function withInitialState(string $state): self
    {
        $this->initialState = $state;
        return $this;
    }
    
    public function withFinalStates(string ...$states): self
    {
        $this->finalStates = $states;
        return $this;
    }
    
    public function withTransition(string $from, string $input, string $to): self
    {
        $this->transitions[] = [$from, $input, $to];
        return $this;
    }
    
    /**
     * @param array<string, string> $transitions Map of "state:symbol" => "nextState"
     */
    public function withTransitions(array $transitions): self
    {
        foreach ($transitions as $key => $to) {
            [$from, $input] = explode(':', $key);
            $this->transitions[] = [$from, $input, $to];
        }
        return $this;
    }
    
    public function build(): FiniteAutomaton
    {
        if (empty($this->states)) {
            throw new InvalidAutomatonException('No states defined');
        }
        
        if (empty($this->alphabet)) {
            throw new InvalidAutomatonException('No alphabet defined');
        }
        
        if ($this->initialState === null) {
            throw new InvalidAutomatonException('No initial state defined');
        }
        
        $stateObjects = [];
        foreach ($this->states as $stateName) {
            $stateObjects[$stateName] = new State($stateName);
        }
        
        $symbolObjects = [];
        foreach ($this->alphabet as $symbolValue) {
            $symbolObjects[$symbolValue] = new Symbol($symbolValue);
        }
        
        $stateSet = new StateSet(...array_values($stateObjects));
        
        $alphabet = new Alphabet(...array_values($symbolObjects));
        
        if (!isset($stateObjects[$this->initialState])) {
            throw new InvalidAutomatonException("Initial state '{$this->initialState}' not in state set");
        }
        $initialState = $stateObjects[$this->initialState];
        
        $finalStateObjects = [];
        foreach ($this->finalStates as $stateName) {
            if (!isset($stateObjects[$stateName])) {
                throw new InvalidAutomatonException("Final state '{$stateName}' not in state set");
            }
            $finalStateObjects[] = $stateObjects[$stateName];
        }
        $finalStates = new StateSet(...$finalStateObjects);
        
        $transitionFunction = new TransitionFunction();
        foreach ($this->transitions as [$from, $input, $to]) {
            if (!isset($stateObjects[$from])) {
                throw new InvalidAutomatonException("Transition source state '{$from}' not in state set");
            }
            if (!isset($symbolObjects[$input])) {
                throw new InvalidAutomatonException("Transition input '{$input}' not in alphabet");
            }
            if (!isset($stateObjects[$to])) {
                throw new InvalidAutomatonException("Transition target state '{$to}' not in state set");
            }
            
            $transitionFunction->define(
                $stateObjects[$from],
                $symbolObjects[$input],
                $stateObjects[$to]
            );
        }
        
        return new FiniteAutomaton(
            $stateSet,
            $alphabet,
            $initialState,
            $finalStates,
            $transitionFunction
        );
    }
}