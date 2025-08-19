<?php

declare(strict_types=1);

namespace FSM\Core\Model;

use FSM\Core\ValueObject\StateSet;
use FSM\Core\ValueObject\Alphabet;
use FSM\Core\ValueObject\State;
use FSM\Core\ValueObject\TransitionFunction;
use FSM\Core\ValueObject\InputString;
use FSM\Core\ValueObject\Symbol;
use FSM\Core\Result\ComputationResult;
use FSM\Core\Result\TransitionRecord;
use FSM\Core\Exception\InvalidAutomatonException;
use FSM\Core\Exception\InvalidInputException;
use FSM\Core\Exception\InvalidTransitionException;

final class FiniteAutomaton
{
    /**
     * @param StateSet $states (Q) - Finite set of states
     * @param Alphabet $alphabet (Σ) - Finite input alphabet
     * @param State $initialState (q0) - Initial state, q0 ∈ Q
     * @param StateSet $finalStates (F) - Set of final states, F ⊆ Q
     * @param TransitionFunction $transitionFunction (δ) - Transition function δ: Q × Σ → Q
     */
    public function __construct(
        private readonly StateSet $states,
        private readonly Alphabet $alphabet,
        private readonly State $initialState,
        private readonly StateSet $finalStates,
        private readonly TransitionFunction $transitionFunction
    ) {
        $this->validate();
    }
    
    private function validate(): void
    {
        if (!$this->states->contains($this->initialState)) {
            throw new InvalidAutomatonException('Initial state must be in the state set');
        }
        
        if (!$this->states->containsAll($this->finalStates)) {
            throw new InvalidAutomatonException('All final states must be in the state set');
        }
        
        $this->transitionFunction->validate($this->states, $this->alphabet);
    }
    
    public function execute(InputString $input): ComputationResult
    {
        $currentState = $this->initialState;
        $transitions = [];
        
        foreach ($input->symbols() as $symbol) {
            if (!$this->alphabet->contains($symbol)) {
                throw new InvalidInputException("Symbol '{$symbol}' not in alphabet");
            }
            
            $nextState = $this->transitionFunction->apply($currentState, $symbol);
            if ($nextState === null) {
                throw new InvalidTransitionException(
                    "No transition from {$currentState} with input {$symbol}"
                );
            }
            
            $transitions[] = new TransitionRecord($currentState, $symbol, $nextState);
            $currentState = $nextState;
        }
        
        return new ComputationResult(
            finalState: $currentState,
            isAccepted: $this->finalStates->contains($currentState),
            transitions: $transitions
        );
    }
    
    public function getStates(): StateSet { return $this->states; }
    public function getAlphabet(): Alphabet { return $this->alphabet; }
    public function getInitialState(): State { return $this->initialState; }
    public function getFinalStates(): StateSet { return $this->finalStates; }
    public function getTransitionFunction(): TransitionFunction { return $this->transitionFunction; }
}