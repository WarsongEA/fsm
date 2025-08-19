<?php

declare(strict_types=1);

namespace FSM\Core\Performance;

use FSM\Core\Model\FiniteAutomaton;

final class CompiledAutomaton
{
    public function __construct(
        public readonly array $states,           
        public readonly array $stateIndices,     
        public readonly array $symbolIndices,    
        public readonly array $transitionTable,  
        public readonly int $initialStateIndex,
        public readonly array $finalStateIndices
    ) {
    }
    
    public function getStateByIndex(int $index): \FSM\Core\ValueObject\State
    {
        if (!isset($this->states[$index])) {
            throw new \OutOfBoundsException("State index {$index} does not exist");
        }
        return new \FSM\Core\ValueObject\State($this->states[$index]);
    }
    
    public static function compile(FiniteAutomaton $automaton): self
    {
        $states = $automaton->getStates()->toArray();
        $stateIndices = array_flip($states);
        
        $alphabet = $automaton->getAlphabet()->toArray();
        $symbolIndices = array_flip($alphabet);
        
        $stateCount = count($states);
        $symbolCount = count($alphabet);
        $table = array_fill(0, $stateCount, array_fill(0, $symbolCount, -1));
        
        foreach ($automaton->getTransitionFunction()->toArray() as $transition) {
            $fromIdx = $stateIndices[$transition['from']];
            $symbolIdx = $symbolIndices[$transition['input']];
            $toIdx = $stateIndices[$transition['to']];
            $table[$fromIdx][$symbolIdx] = $toIdx;
        }
        
        $initialStateIndex = $stateIndices[(string)$automaton->getInitialState()];
        
        $finalStateIndices = array_map(
            fn($state) => $stateIndices[$state],
            $automaton->getFinalStates()->toArray()
        );
        
        return new self(
            $states,
            $stateIndices,
            $symbolIndices,
            $table,
            $initialStateIndex,
            $finalStateIndices
        );
    }
}