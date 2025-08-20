<?php

declare(strict_types=1);

namespace FSM\Application\Service;

use FSM\Core\Model\FSMInstance;
use FSM\Core\Model\FiniteAutomaton;
use FSM\Core\Performance\CompiledAutomaton;
use FSM\Core\ValueObject\InputString;
use FSM\Core\Exception\InvalidInputException;
use FSM\Core\Exception\InvalidTransitionException;
use FSM\Core\ValueObject\State;

/**
 * FSM Executor service
 * Handles execution with performance optimizations
 */
final class FSMExecutor
{
    /** @var array<string, CompiledAutomaton> */
    private array $compiledAutomata = [];
    
    public function execute(
        FSMInstance $instance,
        InputString $input,
        bool $recordHistory = false
    ): ExecutionResult {
        $startTime = microtime(true);
        
        $automaton = $instance->getAutomaton();
        
        // Use compiled automaton for better performance when history is not needed
        if (!$recordHistory && count($input) > 100) {
            return $this->executeFast($automaton, $input);
        }
        
        // Execute using standard automaton (needed for history recording)
        $result = $automaton->execute($input);
        
        // Update instance state if stateful execution
        if ($recordHistory) {
            $instance->updateState($result->finalState);
            foreach ($result->transitions as $transition) {
                $instance->addToHistory([
                    'from' => (string)$transition->fromState,
                    'input' => (string)$transition->symbol,
                    'to' => (string)$transition->toState,
                    'timestamp' => microtime(true)
                ]);
            }
        }
        
        $instance->incrementExecutionCount();
        
        $executionTimeMs = (microtime(true) - $startTime) * 1000;
        
        return new ExecutionResult(
            finalState: $result->finalState,
            isAccepted: $result->isAccepted,
            transitions: $result->transitions,
            executionTimeMs: $executionTimeMs
        );
    }
    
    public function executeFast(FiniteAutomaton $automaton, InputString $input): ExecutionResult
    {
        $startTime = microtime(true);
        
        // Use compiled automaton for performance
        $compiled = $this->getCompiled($automaton);
        
        $stateIndex = $compiled->initialStateIndex;
        
        foreach ($input as $symbol) {
            $symbolIndex = $compiled->symbolIndices[(string)$symbol] ?? -1;
            if ($symbolIndex === -1) {
                throw new InvalidInputException("Symbol '{$symbol}' not in alphabet");
            }
            
            $nextStateIndex = $compiled->transitionTable[$stateIndex][$symbolIndex];
            if ($nextStateIndex === -1) {
                throw new InvalidTransitionException(
                    "No transition from state index {$stateIndex} with symbol '{$symbol}'"
                );
            }
            
            $stateIndex = $nextStateIndex;
        }
        
        $executionTimeMs = (microtime(true) - $startTime) * 1000;
        
        return new ExecutionResult(
            finalState: $compiled->getStateByIndex($stateIndex),
            isAccepted: in_array($stateIndex, $compiled->finalStateIndices, true),
            transitions: [], // No history in fast path
            executionTimeMs: $executionTimeMs
        );
    }
    
    private function getCompiled(FiniteAutomaton $automaton): CompiledAutomaton
    {
        $hash = $this->getAutomatonHash($automaton);
        
        if (!isset($this->compiledAutomata[$hash])) {
            $this->compiledAutomata[$hash] = CompiledAutomaton::compile($automaton);
        }
        
        return $this->compiledAutomata[$hash];
    }
    
    private function getAutomatonHash(FiniteAutomaton $automaton): string
    {
        return md5(json_encode([
            'states' => $automaton->getStates()->toArray(),
            'alphabet' => $automaton->getAlphabet()->toArray(),
            'initial_state' => (string)$automaton->getInitialState(),
            'final_states' => $automaton->getFinalStates()->toArray(),
            'transitions' => $automaton->getTransitionFunction()->toArray()
        ], JSON_THROW_ON_ERROR));
    }
}

/**
 * Execution result with performance metrics
 */
final class ExecutionResult
{
    /**
     * @param array<\FSM\Core\Result\TransitionRecord> $transitions
     */
    public function __construct(
        public readonly State $finalState,
        public readonly bool $isAccepted,
        public readonly array $transitions,
        public readonly float $executionTimeMs
    ) {
    }
}