<?php

declare(strict_types=1);

namespace FSM\Core\Model;

use FSM\Core\ValueObject\State;

/**
 * FSM instance with state management and metadata
 */
final class FSMInstance
{
    private State $currentState;
    private array $history = [];
    private int $version = 1;
    
    public function __construct(
        private readonly string $id,
        private readonly FiniteAutomaton $automaton,
        private readonly FSMMetadata $metadata
    ) {
        $this->currentState = $automaton->getInitialState();
    }
    
    public static function restore(
        string $id,
        FiniteAutomaton $automaton,
        State $currentState,
        array $history,
        int $version,
        FSMMetadata $metadata
    ): self {
        $instance = new self($id, $automaton, $metadata);
        $instance->currentState = $currentState;
        $instance->history = $history;
        $instance->version = $version;
        return $instance;
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getAutomaton(): FiniteAutomaton
    {
        return $this->automaton;
    }
    
    public function getCurrentState(): State
    {
        return $this->currentState;
    }
    
    public function updateState(State $newState): void
    {
        $this->currentState = $newState;
        $this->version++;
    }
    
    public function addToHistory(array $transitionRecord): void
    {
        $this->history[] = $transitionRecord;
        
        // Keep only last 100 transitions
        if (count($this->history) > 100) {
            $this->history = array_slice($this->history, -100);
        }
    }
    
    public function getHistory(): array
    {
        return $this->history;
    }
    
    public function getVersion(): int
    {
        return $this->version;
    }
    
    public function getMetadata(): FSMMetadata
    {
        return $this->metadata;
    }
    
    public function incrementExecutionCount(): void
    {
        $this->metadata->incrementExecutionCount();
    }
}

/**
 * FSM metadata
 */
final class FSMMetadata
{
    private int $executionCount = 0;
    public readonly string $createdAt;
    
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        ?string $createdAt = null
    ) {
        $this->createdAt = $createdAt ?? date('c');
    }
    
    public function incrementExecutionCount(): void
    {
        $this->executionCount++;
    }
    
    public function getExecutionCount(): int
    {
        return $this->executionCount;
    }
}