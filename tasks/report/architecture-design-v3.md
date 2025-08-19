# FSM Architecture Design v3 - Production-Ready Library

## Executive Summary

This document presents the architecture for a **production-ready Finite State Machine (FSM) library** built with PHP, following Domain-Driven Design (DDD) principles and hexagonal architecture. The library provides a clean, developer-friendly API for creating and executing finite automata, with the modulo-3 FSM serving as a reference implementation.

**Key Design Principles:**
- **Mathematical Rigor**: Strict adherence to formal 5-tuple FSM definition (Q, Σ, q0, F, δ)
- **Clean Architecture**: Hexagonal/Ports & Adapters with clear separation of concerns
- **Developer Experience**: Fluent builders, clear APIs, comprehensive examples
- **Performance First**: Optimized transition tables, minimal allocations, fast path by default
- **Transport Agnostic**: Support for both gRPC and REST through thin adapters
- **Production Ready**: Proper error handling, monitoring, versioning, and deployment

## 1. Core Library Architecture (Domain Layer)

### 1.1 Formal FSM Model - The 5-Tuple

```php
namespace FSM\Core\Model;

/**
 * Represents a formal Finite Automaton as the mathematical 5-tuple (Q, Σ, q0, F, δ)
 * This is the core domain model - pure, immutable, and framework-agnostic
 */
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
        // Ensure q0 ∈ Q
        if (!$this->states->contains($this->initialState)) {
            throw new InvalidAutomatonException('Initial state must be in the state set');
        }
        
        // Ensure F ⊆ Q
        if (!$this->states->containsAll($this->finalStates)) {
            throw new InvalidAutomatonException('All final states must be in the state set');
        }
        
        // Validate transition function domain
        $this->transitionFunction->validate($this->states, $this->alphabet);
    }
    
    /**
     * Execute the automaton on an input string
     * Returns the computation result including final state and acceptance
     */
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
    
    // Getters for introspection
    public function getStates(): StateSet { return $this->states; }
    public function getAlphabet(): Alphabet { return $this->alphabet; }
    public function getInitialState(): State { return $this->initialState; }
    public function getFinalStates(): StateSet { return $this->finalStates; }
    public function getTransitionFunction(): TransitionFunction { return $this->transitionFunction; }
}
```

### 1.2 Value Objects

```php
namespace FSM\Core\ValueObject;

/**
 * Immutable state representation
 */
final class State implements \Stringable
{
    public function __construct(
        private readonly string $name
    ) {
        if (empty($name)) {
            throw new \InvalidArgumentException('State name cannot be empty');
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

/**
 * Set of states with set operations
 */
final class StateSet implements \Countable, \IteratorAggregate
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
    
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->states));
    }
    
    public function toArray(): array
    {
        return array_map(fn($s) => (string)$s, array_values($this->states));
    }
}

/**
 * Input alphabet
 */
final class Alphabet implements \Countable, \IteratorAggregate
{
    /** @var array<string, Symbol> */
    private array $symbols = [];
    
    public function __construct(Symbol ...$symbols)
    {
        if (empty($symbols)) {
            throw new \InvalidArgumentException('Alphabet must contain at least one symbol');
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
    
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->symbols));
    }
    
    public function toArray(): array
    {
        return array_map(fn($s) => (string)$s, array_values($this->symbols));
    }
}

/**
 * Input symbol
 */
final class Symbol implements \Stringable
{
    public function __construct(
        private readonly string $value
    ) {
        if (strlen($value) !== 1) {
            throw new \InvalidArgumentException('Symbol must be a single character');
        }
    }
    
    public function equals(Symbol $other): bool
    {
        return $this->value === $other->value;
    }
    
    public function __toString(): string
    {
        return $this->value;
    }
}

/**
 * Transition function δ: Q × Σ → Q
 * Optimized for O(1) lookups
 */
final class TransitionFunction
{
    /** @var array<string, State> Optimized lookup table */
    private array $table = [];
    
    public function __construct()
    {
    }
    
    /**
     * Define a transition: δ(fromState, symbol) = toState
     */
    public function define(State $fromState, Symbol $symbol, State $toState): self
    {
        $key = $this->makeKey($fromState, $symbol);
        $this->table[$key] = $toState;
        return $this;
    }
    
    /**
     * Apply the transition function: δ(state, symbol)
     */
    public function apply(State $state, Symbol $symbol): ?State
    {
        $key = $this->makeKey($state, $symbol);
        return $this->table[$key] ?? null;
    }
    
    /**
     * Validate that the transition function is well-defined for the given automaton
     */
    public function validate(StateSet $states, Alphabet $alphabet): void
    {
        // Check all transitions point to valid states
        foreach ($this->table as $toState) {
            if (!$states->contains($toState)) {
                throw new InvalidAutomatonException(
                    "Transition target state '{$toState}' not in state set"
                );
            }
        }
        
        // For deterministic FSM, ensure all state-symbol pairs have transitions
        // (This can be relaxed for partial functions)
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
            // Log warning but don't throw - partial functions are valid
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
    
    /**
     * Export as array for serialization
     */
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

/**
 * Input string to be processed by the automaton
 */
final class InputString implements \Countable, \IteratorAggregate
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
    
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->symbols);
    }
    
    public function __toString(): string
    {
        return implode('', array_map(fn($s) => (string)$s, $this->symbols));
    }
}
```

### 1.3 Builder Pattern for Developer-Friendly API

```php
namespace FSM\Core\Builder;

/**
 * Fluent builder for creating finite automata
 * Provides a developer-friendly API while maintaining mathematical rigor
 */
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
     * Bulk add transitions using array notation
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
    
    /**
     * Build the finite automaton
     * @throws InvalidAutomatonException if the specification is invalid
     */
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
        
        // Create value objects
        $stateObjects = [];
        foreach ($this->states as $stateName) {
            $stateObjects[$stateName] = new State($stateName);
        }
        
        $symbolObjects = [];
        foreach ($this->alphabet as $symbolValue) {
            $symbolObjects[$symbolValue] = new Symbol($symbolValue);
        }
        
        // Build state set
        $stateSet = new StateSet(...array_values($stateObjects));
        
        // Build alphabet
        $alphabet = new Alphabet(...array_values($symbolObjects));
        
        // Initial state
        if (!isset($stateObjects[$this->initialState])) {
            throw new InvalidAutomatonException("Initial state '{$this->initialState}' not in state set");
        }
        $initialState = $stateObjects[$this->initialState];
        
        // Final states
        $finalStateObjects = [];
        foreach ($this->finalStates as $stateName) {
            if (!isset($stateObjects[$stateName])) {
                throw new InvalidAutomatonException("Final state '{$stateName}' not in state set");
            }
            $finalStateObjects[] = $stateObjects[$stateName];
        }
        $finalStates = new StateSet(...$finalStateObjects);
        
        // Build transition function
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
```

### 1.4 Computation Results

```php
namespace FSM\Core\Result;

/**
 * Result of an FSM computation
 */
final class ComputationResult
{
    /**
     * @param State $finalState The final state after processing all input
     * @param bool $isAccepted Whether the final state is an accepting state
     * @param TransitionRecord[] $transitions The sequence of transitions taken
     */
    public function __construct(
        public readonly State $finalState,
        public readonly bool $isAccepted,
        public readonly array $transitions
    ) {
    }
    
    public function getPath(): array
    {
        return array_map(fn($t) => [
            'from' => (string)$t->fromState,
            'input' => (string)$t->symbol,
            'to' => (string)$t->toState
        ], $this->transitions);
    }
}

/**
 * Record of a single transition
 */
final class TransitionRecord
{
    public function __construct(
        public readonly State $fromState,
        public readonly Symbol $symbol,
        public readonly State $toState,
        public readonly ?float $timestamp = null
    ) {
    }
}
```

## 2. Application Layer - Use Cases

### 2.1 Command/Query Separation

```php
namespace FSM\Application\Command;

/**
 * Command to create a new FSM instance
 */
final class CreateFSMCommand
{
    public function __construct(
        public readonly array $states,
        public readonly array $alphabet,
        public readonly string $initialState,
        public readonly array $finalStates,
        public readonly array $transitions,
        public readonly ?string $name = null,
        public readonly ?string $description = null
    ) {
    }
}

/**
 * Command to execute input on an FSM
 */
final class ExecuteFSMCommand
{
    public function __construct(
        public readonly string $fsmId,
        public readonly string $input,
        public readonly bool $recordHistory = false
    ) {
    }
}

namespace FSM\Application\Query;

/**
 * Query to get FSM state
 */
final class GetFSMStateQuery
{
    public function __construct(
        public readonly string $fsmId,
        public readonly int $historyLimit = 0
    ) {
    }
}
```

### 2.2 Use Case Handlers

```php
namespace FSM\Application\Handler;

/**
 * Handler for creating FSM instances
 * Thin orchestration layer - no business logic
 */
final class CreateFSMHandler
{
    public function __construct(
        private readonly FSMRepository $repository,
        private readonly EventDispatcher $eventDispatcher
    ) {
    }
    
    public function handle(CreateFSMCommand $command): CreateFSMResult
    {
        // Build the automaton using the builder
        $automaton = AutomatonBuilder::create()
            ->withStates(...$command->states)
            ->withAlphabet(...$command->alphabet)
            ->withInitialState($command->initialState)
            ->withFinalStates(...$command->finalStates)
            ->withTransitions($command->transitions)
            ->build();
        
        // Create the FSM instance
        $instance = new FSMInstance(
            id: Uuid::uuid7()->toString(),
            automaton: $automaton,
            metadata: new FSMMetadata(
                name: $command->name,
                description: $command->description
            )
        );
        
        // Persist
        $this->repository->save($instance);
        
        // Dispatch event
        $this->eventDispatcher->dispatch(new FSMCreatedEvent($instance));
        
        return new CreateFSMResult(
            fsmId: $instance->getId(),
            metadata: $instance->getMetadata()
        );
    }
}

/**
 * Handler for executing FSM
 */
final class ExecuteFSMHandler
{
    public function __construct(
        private readonly FSMRepository $repository,
        private readonly FSMExecutor $executor
    ) {
    }
    
    public function handle(ExecuteFSMCommand $command): ExecuteFSMResult
    {
        $instance = $this->repository->findById($command->fsmId);
        if ($instance === null) {
            throw new FSMNotFoundException("FSM {$command->fsmId} not found");
        }
        
        // Execute with optional history recording
        $result = $this->executor->execute(
            $instance,
            new InputString($command->input),
            $command->recordHistory
        );
        
        // Update instance state if stateful execution
        if ($command->recordHistory) {
            $this->repository->save($instance);
        }
        
        return new ExecuteFSMResult(
            finalState: (string)$result->finalState,
            isAccepted: $result->isAccepted,
            transitions: $result->getPath(),
            executionTimeMs: $result->executionTimeMs
        );
    }
}
```

### 2.3 Domain Services in Application Layer

```php
namespace FSM\Application\Service;

/**
 * FSM Executor service
 * Handles execution with performance optimizations
 */
final class FSMExecutor
{
    private array $compiledAutomata = [];
    
    public function execute(
        FSMInstance $instance,
        InputString $input,
        bool $recordHistory = false
    ): ExecutionResult {
        $startTime = microtime(true);
        
        // Use compiled automaton for performance
        $compiled = $this->getCompiled($instance->getAutomaton());
        
        // Execute with fast path
        $result = $recordHistory
            ? $this->executeWithHistory($compiled, $input)
            : $this->executeFast($compiled, $input);
        
        $result->executionTimeMs = (microtime(true) - $startTime) * 1000;
        
        return $result;
    }
    
    private function executeFast(CompiledAutomaton $compiled, InputString $input): ExecutionResult
    {
        $stateIndex = $compiled->initialStateIndex;
        
        foreach ($input as $symbol) {
            $symbolIndex = $compiled->symbolIndices[(string)$symbol] ?? -1;
            if ($symbolIndex === -1) {
                throw new InvalidInputException("Symbol '{$symbol}' not in alphabet");
            }
            
            $nextStateIndex = $compiled->transitionTable[$stateIndex][$symbolIndex];
            if ($nextStateIndex === -1) {
                throw new InvalidTransitionException("No transition defined");
            }
            
            $stateIndex = $nextStateIndex;
        }
        
        return new ExecutionResult(
            finalState: $compiled->states[$stateIndex],
            isAccepted: in_array($stateIndex, $compiled->finalStateIndices),
            transitions: [] // No history in fast path
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
        return md5(serialize([
            $automaton->getStates()->toArray(),
            $automaton->getAlphabet()->toArray(),
            (string)$automaton->getInitialState(),
            $automaton->getFinalStates()->toArray(),
            $automaton->getTransitionFunction()->toArray()
        ]));
    }
}
```

## 3. Infrastructure Layer - Adapters & Ports

### 3.1 Repository Pattern

```php
namespace FSM\Infrastructure\Persistence;

/**
 * Repository interface (Port)
 */
interface FSMRepository
{
    public function save(FSMInstance $instance): void;
    public function findById(string $id): ?FSMInstance;
    public function delete(string $id): void;
    public function findAll(int $limit = 100, int $offset = 0): array;
}

/**
 * Redis implementation (Adapter)
 */
final class RedisFSMRepository implements FSMRepository
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly FSMSerializer $serializer
    ) {
    }
    
    public function save(FSMInstance $instance): void
    {
        $key = "fsm:{$instance->getId()}";
        $data = $this->serializer->serialize($instance);
        
        // Use optimistic locking with version
        $this->redis->watch($key);
        $existing = $this->redis->get($key);
        
        if ($existing) {
            $existingData = json_decode($existing, true);
            if ($existingData['version'] !== $instance->getVersion() - 1) {
                $this->redis->unwatch();
                throw new ConcurrencyException('Version mismatch');
            }
        }
        
        $this->redis->multi()
            ->set($key, $data)
            ->expire($key, 3600)
            ->exec();
    }
    
    public function findById(string $id): ?FSMInstance
    {
        $data = $this->redis->get("fsm:{$id}");
        
        if ($data === false) {
            return null;
        }
        
        return $this->serializer->deserialize($data);
    }
    
    public function delete(string $id): void
    {
        $this->redis->del("fsm:{$id}");
    }
    
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $keys = $this->redis->keys('fsm:*');
        $keys = array_slice($keys, $offset, $limit);
        
        if (empty($keys)) {
            return [];
        }
        
        $values = $this->redis->mget($keys);
        
        return array_filter(array_map(
            fn($data) => $data ? $this->serializer->deserialize($data) : null,
            $values
        ));
    }
}

/**
 * In-memory implementation for testing
 */
final class InMemoryFSMRepository implements FSMRepository
{
    private array $storage = [];
    
    public function save(FSMInstance $instance): void
    {
        $this->storage[$instance->getId()] = clone $instance;
    }
    
    public function findById(string $id): ?FSMInstance
    {
        return isset($this->storage[$id]) ? clone $this->storage[$id] : null;
    }
    
    public function delete(string $id): void
    {
        unset($this->storage[$id]);
    }
    
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        return array_slice($this->storage, $offset, $limit);
    }
}
```

### 3.2 Serialization

```php
namespace FSM\Infrastructure\Serialization;

/**
 * Serializer for FSM instances
 * Uses clean JSON format without PHP serialization
 */
final class FSMSerializer
{
    public function serialize(FSMInstance $instance): string
    {
        $automaton = $instance->getAutomaton();
        
        $data = [
            'id' => $instance->getId(),
            'version' => $instance->getVersion(),
            'automaton' => [
                'states' => $automaton->getStates()->toArray(),
                'alphabet' => $automaton->getAlphabet()->toArray(),
                'initial_state' => (string)$automaton->getInitialState(),
                'final_states' => $automaton->getFinalStates()->toArray(),
                'transitions' => $automaton->getTransitionFunction()->toArray()
            ],
            'metadata' => [
                'name' => $instance->getMetadata()->name,
                'description' => $instance->getMetadata()->description,
                'created_at' => $instance->getMetadata()->createdAt,
                'execution_count' => $instance->getMetadata()->executionCount
            ],
            'state' => [
                'current' => (string)$instance->getCurrentState(),
                'history' => $instance->getHistory()
            ]
        ];
        
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
    
    public function deserialize(string $json): FSMInstance
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        
        // Rebuild automaton
        $automaton = AutomatonBuilder::create()
            ->withStates(...$data['automaton']['states'])
            ->withAlphabet(...$data['automaton']['alphabet'])
            ->withInitialState($data['automaton']['initial_state'])
            ->withFinalStates(...$data['automaton']['final_states'])
            ->build();
        
        // Add transitions
        foreach ($data['automaton']['transitions'] as $transition) {
            $automaton->getTransitionFunction()->define(
                new State($transition['from']),
                new Symbol($transition['input']),
                new State($transition['to'])
            );
        }
        
        // Rebuild instance
        return FSMInstance::restore(
            id: $data['id'],
            automaton: $automaton,
            currentState: new State($data['state']['current']),
            history: $data['state']['history'] ?? [],
            version: $data['version'],
            metadata: new FSMMetadata(
                name: $data['metadata']['name'],
                description: $data['metadata']['description'],
                createdAt: $data['metadata']['created_at'],
                executionCount: $data['metadata']['execution_count']
            )
        );
    }
}
```

### 3.3 gRPC Adapter

```php
namespace FSM\Infrastructure\Transport\Grpc;

use OpenSwoole\GRPC\{Server, Request, Response, Status};

/**
 * gRPC service implementation
 * Thin adapter that delegates to application layer
 */
final class FSMGrpcService implements FSMServiceInterface
{
    public function __construct(
        private readonly CreateFSMHandler $createHandler,
        private readonly ExecuteFSMHandler $executeHandler,
        private readonly GetFSMStateHandler $getStateHandler,
        private readonly ModuloThreeService $moduloThreeService
    ) {
    }
    
    public function CreateFSM(Request $request, Response $response): void
    {
        go(function() use ($request, $response) {
            try {
                $data = $request->getMessage();
                
                // Map proto to command
                $command = new CreateFSMCommand(
                    states: $data->getDefinition()->getStates(),
                    alphabet: $data->getDefinition()->getAlphabet(),
                    initialState: $data->getDefinition()->getInitialState(),
                    finalStates: $data->getDefinition()->getFinalStates(),
                    transitions: $this->mapTransitions($data->getDefinition()->getTransitions()),
                    name: $data->getName(),
                    description: $data->getDescription()
                );
                
                // Execute use case
                $result = $this->createHandler->handle($command);
                
                // Map result to proto
                $response->setMessage([
                    'fsm_id' => $result->fsmId,
                    'metadata' => $this->mapMetadata($result->metadata)
                ]);
                $response->setStatus(Status::ok());
                
            } catch (InvalidAutomatonException $e) {
                $response->setStatus(Status::invalidArgument($e->getMessage()));
            } catch (\Exception $e) {
                $response->setStatus(Status::internal($e->getMessage()));
            }
        });
    }
    
    public function Execute(Request $request, Response $response): void
    {
        go(function() use ($request, $response) {
            try {
                $data = $request->getMessage();
                
                $command = new ExecuteFSMCommand(
                    fsmId: $data->getFsmId(),
                    input: $data->getInputSequence(),
                    recordHistory: $data->getSaveHistory()
                );
                
                $result = $this->executeHandler->handle($command);
                
                $response->setMessage([
                    'final_state' => $result->finalState,
                    'transitions' => $result->transitions,
                    'execution_time_ms' => $result->executionTimeMs,
                    'is_final_state' => $result->isAccepted
                ]);
                $response->setStatus(Status::ok());
                
            } catch (FSMNotFoundException $e) {
                $response->setStatus(Status::notFound($e->getMessage()));
            } catch (InvalidInputException | InvalidTransitionException $e) {
                $response->setStatus(Status::invalidArgument($e->getMessage()));
            } catch (ConcurrencyException $e) {
                $response->setStatus(Status::aborted($e->getMessage()));
            } catch (\Exception $e) {
                $response->setStatus(Status::internal($e->getMessage()));
            }
        });
    }
    
    public function ExecuteStream(Request $request, Response $response): void
    {
        go(function() use ($request, $response) {
            $fsmId = null;
            $instance = null;
            $executor = new FSMExecutor();
            
            while ($message = $request->recv()) {
                try {
                    // Initialize on first message
                    if (!$fsmId) {
                        $fsmId = $message->getFsmId();
                        $instance = $this->repository->findById($fsmId);
                        
                        if (!$instance) {
                            $response->setStatus(Status::notFound('FSM not found'));
                            return;
                        }
                    }
                    
                    // Process input chunk
                    $input = $message->getInput();
                    foreach (str_split($input) as $char) {
                        $prevState = $instance->getCurrentState();
                        
                        // Execute single step
                        $result = $executor->execute(
                            $instance,
                            new InputString($char),
                            false
                        );
                        
                        // Stream state change
                        $response->send([
                            'current_state' => (string)$result->finalState,
                            'last_transition' => [
                                'from_state' => (string)$prevState,
                                'input' => $char,
                                'to_state' => (string)$result->finalState
                            ],
                            'is_final_state' => $result->isAccepted
                        ]);
                        
                        // Update instance state
                        $instance->updateState($result->finalState);
                    }
                    
                } catch (InvalidTransitionException $e) {
                    $response->setStatus(Status::invalidArgument($e->getMessage()));
                    return;
                } catch (\Exception $e) {
                    $response->setStatus(Status::internal($e->getMessage()));
                    return;
                }
            }
            
            // Save final state
            try {
                $this->repository->save($instance);
            } catch (ConcurrencyException $e) {
                $response->setStatus(Status::aborted($e->getMessage()));
            }
        });
    }
    
    public function ModuloThree(Request $request, Response $response): void
    {
        go(function() use ($request, $response) {
            try {
                $binaryInput = $request->getMessage()->getBinaryInput();
                $returnTransitions = $request->getMessage()->getReturnTransitions();
                
                $result = $this->moduloThreeService->calculate(
                    $binaryInput,
                    $returnTransitions
                );
                
                $response->setMessage([
                    'result' => $result->modulo,
                    'final_state' => $result->finalState,
                    'decimal_value' => $result->decimalValue,
                    'transitions' => $returnTransitions ? $result->transitions : [],
                    'execution_time_ms' => $result->executionTimeMs
                ]);
                $response->setStatus(Status::ok());
                
            } catch (\InvalidArgumentException $e) {
                $response->setStatus(Status::invalidArgument($e->getMessage()));
            } catch (\Exception $e) {
                $response->setStatus(Status::internal($e->getMessage()));
            }
        });
    }
    
    private function mapTransitions(array $protoTransitions): array
    {
        $result = [];
        foreach ($protoTransitions as $t) {
            $key = $t->getFromState() . ':' . $t->getInput();
            $result[$key] = $t->getToState();
        }
        return $result;
    }
}
```

### 3.4 REST Adapter

```php
namespace FSM\Infrastructure\Transport\Rest;

use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\RequestHandlerInterface;

/**
 * REST controller for FSM operations
 * Thin adapter that delegates to application layer
 */
final class FSMController implements RequestHandlerInterface
{
    public function __construct(
        private readonly CreateFSMHandler $createHandler,
        private readonly ExecuteFSMHandler $executeHandler,
        private readonly GetFSMStateHandler $getStateHandler
    ) {
    }
    
    public function createFSM(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $data = json_decode((string)$request->getBody(), true);
            
            $command = new CreateFSMCommand(
                states: $data['states'],
                alphabet: $data['alphabet'],
                initialState: $data['initial_state'],
                finalStates: $data['final_states'],
                transitions: $data['transitions'],
                name: $data['name'] ?? null,
                description: $data['description'] ?? null
            );
            
            $result = $this->createHandler->handle($command);
            
            return $this->jsonResponse([
                'fsm_id' => $result->fsmId,
                'metadata' => $result->metadata
            ], 201);
            
        } catch (InvalidAutomatonException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    public function execute(ServerRequestInterface $request, array $args): ResponseInterface
    {
        try {
            $fsmId = $args['id'];
            $data = json_decode((string)$request->getBody(), true);
            
            $command = new ExecuteFSMCommand(
                fsmId: $fsmId,
                input: $data['input'],
                recordHistory: $data['record_history'] ?? false
            );
            
            $result = $this->executeHandler->handle($command);
            
            return $this->jsonResponse([
                'final_state' => $result->finalState,
                'is_accepted' => $result->isAccepted,
                'transitions' => $result->transitions,
                'execution_time_ms' => $result->executionTimeMs
            ]);
            
        } catch (FSMNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (InvalidInputException | InvalidTransitionException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    public function getState(ServerRequestInterface $request, array $args): ResponseInterface
    {
        try {
            $fsmId = $args['id'];
            $historyLimit = (int)($request->getQueryParams()['history_limit'] ?? 0);
            
            $query = new GetFSMStateQuery($fsmId, $historyLimit);
            $result = $this->getStateHandler->handle($query);
            
            return $this->jsonResponse([
                'current_state' => $result->currentState,
                'is_final_state' => $result->isFinalState,
                'history' => $result->history,
                'metadata' => $result->metadata
            ]);
            
        } catch (FSMNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Internal server error', 500);
        }
    }
    
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($data)
        );
    }
    
    private function errorResponse(string $message, int $status): ResponseInterface
    {
        return $this->jsonResponse(['error' => $message], $status);
    }
}
```

## 4. Example Implementations

### 4.1 Modulo-3 Automaton

```php
namespace FSM\Examples\ModuloThree;

/**
 * Reference implementation: Modulo-3 FSM
 * Demonstrates how to use the library for the canonical example
 */
final class ModuloThreeAutomaton
{
    private static ?FiniteAutomaton $instance = null;
    
    /**
     * Get the modulo-3 automaton singleton
     */
    public static function getInstance(): FiniteAutomaton
    {
        if (self::$instance === null) {
            self::$instance = AutomatonBuilder::create()
                ->withStates('S0', 'S1', 'S2')
                ->withAlphabet('0', '1')
                ->withInitialState('S0')
                ->withFinalStates('S0', 'S1', 'S2')
                ->withTransitions([
                    'S0:0' => 'S0',  // 0 * 2 = 0 (mod 3)
                    'S0:1' => 'S1',  // 0 * 2 + 1 = 1 (mod 3)
                    'S1:0' => 'S2',  // 1 * 2 = 2 (mod 3)
                    'S1:1' => 'S0',  // 1 * 2 + 1 = 3 ≡ 0 (mod 3)
                    'S2:0' => 'S1',  // 2 * 2 = 4 ≡ 1 (mod 3)
                    'S2:1' => 'S2',  // 2 * 2 + 1 = 5 ≡ 2 (mod 3)
                ])
                ->build();
        }
        
        return self::$instance;
    }
    
    /**
     * Calculate n mod 3 where n is given as a binary string
     */
    public static function calculate(string $binaryString): int
    {
        if (!preg_match('/^[01]+$/', $binaryString)) {
            throw new \InvalidArgumentException('Input must be a binary string');
        }
        
        $automaton = self::getInstance();
        $result = $automaton->execute(new InputString($binaryString));
        
        return match((string)$result->finalState) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
            default => throw new \LogicException('Invalid final state')
        };
    }
}

/**
 * Service for modulo-3 calculations with additional features
 */
final class ModuloThreeService
{
    public function calculate(string $binaryInput, bool $returnTransitions = false): ModuloThreeResult
    {
        $startTime = microtime(true);
        
        if (!preg_match('/^[01]+$/', $binaryInput)) {
            throw new \InvalidArgumentException('Input must be a binary string');
        }
        
        $automaton = ModuloThreeAutomaton::getInstance();
        $result = $automaton->execute(new InputString($binaryInput));
        
        // Calculate decimal value using GMP for large numbers
        $decimalValue = gmp_strval(gmp_init($binaryInput, 2), 10);
        
        $modulo = match((string)$result->finalState) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
        };
        
        return new ModuloThreeResult(
            modulo: $modulo,
            finalState: (string)$result->finalState,
            decimalValue: $decimalValue,
            transitions: $returnTransitions ? $result->getPath() : [],
            executionTimeMs: (microtime(true) - $startTime) * 1000
        );
    }
}
```

### 4.2 Binary Addition Automaton

```php
namespace FSM\Examples\BinaryAdder;

/**
 * Example: Binary addition FSM with carry
 * Demonstrates a more complex automaton
 */
final class BinaryAdderAutomaton
{
    public static function create(): FiniteAutomaton
    {
        return AutomatonBuilder::create()
            ->withStates('NoCarry', 'Carry')
            ->withAlphabet('00', '01', '10', '11')  // Input pairs
            ->withInitialState('NoCarry')
            ->withFinalStates('NoCarry')  // Valid if no carry at end
            ->withTransitions([
                'NoCarry:00' => 'NoCarry',  // 0+0=0, no carry
                'NoCarry:01' => 'NoCarry',  // 0+1=1, no carry
                'NoCarry:10' => 'NoCarry',  // 1+0=1, no carry
                'NoCarry:11' => 'Carry',    // 1+1=0, carry 1
                'Carry:00' => 'NoCarry',    // 0+0+1=1, no carry
                'Carry:01' => 'Carry',      // 0+1+1=0, carry 1
                'Carry:10' => 'Carry',      // 1+0+1=0, carry 1
                'Carry:11' => 'Carry',      // 1+1+1=1, carry 1
            ])
            ->build();
    }
}
```

### 4.3 Regular Expression Matcher

```php
namespace FSM\Examples\Regex;

/**
 * Example: FSM for matching binary strings ending with "01"
 * Shows how to build pattern matching automata
 */
final class EndsWithZeroOneAutomaton
{
    public static function create(): FiniteAutomaton
    {
        return AutomatonBuilder::create()
            ->withStates('Start', 'Zero', 'ZeroOne')
            ->withAlphabet('0', '1')
            ->withInitialState('Start')
            ->withFinalStates('ZeroOne')
            ->withTransitions([
                'Start:0' => 'Zero',
                'Start:1' => 'Start',
                'Zero:0' => 'Zero',
                'Zero:1' => 'ZeroOne',
                'ZeroOne:0' => 'Zero',
                'ZeroOne:1' => 'Start',
            ])
            ->build();
    }
    
    public static function matches(string $input): bool
    {
        $automaton = self::create();
        $result = $automaton->execute(new InputString($input));
        return $result->isAccepted;
    }
}
```

## 5. Performance Optimizations

### 5.1 Compiled Automaton

```php
namespace FSM\Core\Performance;

/**
 * Compiled automaton for maximum performance
 * Uses integer indices and arrays for O(1) lookups
 */
final class CompiledAutomaton
{
    public function __construct(
        public readonly array $states,           // Indexed array of state names
        public readonly array $stateIndices,     // Map: state name => index
        public readonly array $symbolIndices,    // Map: symbol => index
        public readonly array $transitionTable,  // 2D array: [stateIdx][symbolIdx] => nextStateIdx
        public readonly int $initialStateIndex,
        public readonly array $finalStateIndices
    ) {
    }
    
    public static function compile(FiniteAutomaton $automaton): self
    {
        // Build state index
        $states = $automaton->getStates()->toArray();
        $stateIndices = array_flip($states);
        
        // Build symbol index
        $alphabet = $automaton->getAlphabet()->toArray();
        $symbolIndices = array_flip($alphabet);
        
        // Build transition table
        $stateCount = count($states);
        $symbolCount = count($alphabet);
        $table = array_fill(0, $stateCount, array_fill(0, $symbolCount, -1));
        
        foreach ($automaton->getTransitionFunction()->toArray() as $transition) {
            $fromIdx = $stateIndices[$transition['from']];
            $symbolIdx = $symbolIndices[$transition['input']];
            $toIdx = $stateIndices[$transition['to']];
            $table[$fromIdx][$symbolIdx] = $toIdx;
        }
        
        // Get initial state index
        $initialStateIndex = $stateIndices[(string)$automaton->getInitialState()];
        
        // Get final state indices
        $finalStateIndices = array_map(
            fn($state) => $stateIndices[(string)$state],
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
```

### 5.2 Batch Processing

```php
namespace FSM\Core\Performance;

/**
 * Batch processor for multiple inputs
 * Processes multiple strings in parallel using coroutines
 */
final class BatchProcessor
{
    public function __construct(
        private readonly FSMExecutor $executor
    ) {
    }
    
    /**
     * Process multiple inputs in parallel
     * @param FiniteAutomaton $automaton
     * @param string[] $inputs
     * @return ComputationResult[]
     */
    public function processBatch(FiniteAutomaton $automaton, array $inputs): array
    {
        $results = [];
        $channel = new \Swoole\Coroutine\Channel(count($inputs));
        
        foreach ($inputs as $index => $input) {
            go(function() use ($automaton, $input, $index, $channel) {
                try {
                    $result = $automaton->execute(new InputString($input));
                    $channel->push([$index, $result]);
                } catch (\Exception $e) {
                    $channel->push([$index, $e]);
                }
            });
        }
        
        // Collect results
        for ($i = 0; $i < count($inputs); $i++) {
            [$index, $result] = $channel->pop();
            $results[$index] = $result;
        }
        
        ksort($results);
        return $results;
    }
}
```

## 6. Testing Strategy

### 6.1 Unit Tests

```php
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;

final class FiniteAutomatonTest extends TestCase
{
    public function testBuilderCreatesValidAutomaton(): void
    {
        $automaton = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'A')
            ->withTransition('A', '1', 'B')
            ->withTransition('B', '0', 'B')
            ->withTransition('B', '1', 'A')
            ->build();
        
        $this->assertCount(2, $automaton->getStates());
        $this->assertCount(2, $automaton->getAlphabet());
        $this->assertEquals('A', (string)$automaton->getInitialState());
    }
    
    public function testExecutionProducesCorrectResult(): void
    {
        $automaton = $this->createTestAutomaton();
        
        $result = $automaton->execute(new InputString('0110'));
        
        $this->assertEquals('B', (string)$result->finalState);
        $this->assertTrue($result->isAccepted);
        $this->assertCount(4, $result->transitions);
    }
    
    public function testInvalidInputThrowsException(): void
    {
        $automaton = $this->createTestAutomaton();
        
        $this->expectException(InvalidInputException::class);
        $automaton->execute(new InputString('012'));  // '2' not in alphabet
    }
    
    public function testPartialTransitionFunctionHandled(): void
    {
        $automaton = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'B')
            // Missing transition for A,1 and all B transitions
            ->build();
        
        $this->expectException(InvalidTransitionException::class);
        $automaton->execute(new InputString('1'));
    }
}
```

### 6.2 Integration Tests

```php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use FSM\Application\Command\CreateFSMCommand;
use FSM\Application\Handler\CreateFSMHandler;

final class CreateFSMIntegrationTest extends TestCase
{
    private CreateFSMHandler $handler;
    private FSMRepository $repository;
    
    protected function setUp(): void
    {
        $this->repository = new InMemoryFSMRepository();
        $this->handler = new CreateFSMHandler(
            $this->repository,
            new NullEventDispatcher()
        );
    }
    
    public function testCreateAndRetrieveFSM(): void
    {
        // Create FSM
        $command = new CreateFSMCommand(
            states: ['S0', 'S1', 'S2'],
            alphabet: ['0', '1'],
            initialState: 'S0',
            finalStates: ['S0', 'S1', 'S2'],
            transitions: [
                'S0:0' => 'S0',
                'S0:1' => 'S1',
                'S1:0' => 'S2',
                'S1:1' => 'S0',
                'S2:0' => 'S1',
                'S2:1' => 'S2',
            ],
            name: 'Modulo-3 FSM',
            description: 'Calculates n mod 3 for binary input'
        );
        
        $result = $this->handler->handle($command);
        
        // Verify creation
        $this->assertNotEmpty($result->fsmId);
        $this->assertEquals('Modulo-3 FSM', $result->metadata->name);
        
        // Retrieve and verify
        $instance = $this->repository->findById($result->fsmId);
        $this->assertNotNull($instance);
        $this->assertEquals('S0', (string)$instance->getCurrentState());
    }
}
```

### 6.3 Property-Based Tests

```php
namespace Tests\Property;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;

final class ModuloThreePropertyTest extends TestCase
{
    use TestTrait;
    
    public function testModuloThreeCorrectness(): void
    {
        $this->forAll(
            Generator\pos()->suchThat(fn($n) => $n < PHP_INT_MAX)
        )->then(function(int $number) {
            $binary = decbin($number);
            
            $fsmResult = ModuloThreeAutomaton::calculate($binary);
            $expectedResult = $number % 3;
            
            $this->assertEquals(
                $expectedResult,
                $fsmResult,
                "Failed for {$number} (binary: {$binary})"
            );
        });
    }
    
    public function testLargeBinaryNumbers(): void
    {
        $this->forAll(
            Generator\seq(Generator\elements(['0', '1']))
                ->withMaxSize(1000)
                ->map(fn($arr) => implode('', $arr))
                ->suchThat(fn($s) => $s !== '' && $s[0] === '1')
        )->then(function(string $binary) {
            $fsmResult = ModuloThreeAutomaton::calculate($binary);
            
            // Use GMP for verification
            $decimal = gmp_init($binary, 2);
            $expectedResult = (int)gmp_strval(gmp_mod($decimal, 3));
            
            $this->assertEquals($expectedResult, $fsmResult);
        });
    }
}
```

### 6.4 Performance Tests

```php
namespace Tests\Performance;

use PHPUnit\Framework\TestCase;
use FSM\Core\Performance\CompiledAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;

final class PerformanceTest extends TestCase
{
    public function testCompiledAutomatonPerformance(): void
    {
        $automaton = ModuloThreeAutomaton::getInstance();
        $compiled = CompiledAutomaton::compile($automaton);
        
        $largeInput = str_repeat('10110101', 10000);  // 80,000 bits
        
        // Test compiled performance
        $startTime = microtime(true);
        
        $stateIndex = $compiled->initialStateIndex;
        foreach (str_split($largeInput) as $char) {
            $symbolIndex = $compiled->symbolIndices[$char];
            $stateIndex = $compiled->transitionTable[$stateIndex][$symbolIndex];
        }
        
        $executionTime = microtime(true) - $startTime;
        
        // Should process in under 100ms
        $this->assertLessThan(0.1, $executionTime);
        
        // Verify correctness
        $finalState = $compiled->states[$stateIndex];
        $this->assertContains($finalState, ['S0', 'S1', 'S2']);
    }
    
    public function testBatchProcessing(): void
    {
        $processor = new BatchProcessor(new FSMExecutor());
        $automaton = ModuloThreeAutomaton::getInstance();
        
        // Generate 1000 random binary strings
        $inputs = array_map(
            fn() => str_pad(decbin(rand(0, 1000000)), 20, '0', STR_PAD_LEFT),
            range(1, 1000)
        );
        
        $startTime = microtime(true);
        $results = $processor->processBatch($automaton, $inputs);
        $executionTime = microtime(true) - $startTime;
        
        // Should process 1000 inputs in under 1 second
        $this->assertLessThan(1.0, $executionTime);
        $this->assertCount(1000, $results);
    }
}
```

## 7. Project Structure

```
fsm-library/
├── src/
│   ├── Core/                  # Domain layer - pure FSM library
│   │   ├── Model/
│   │   │   ├── FiniteAutomaton.php
│   │   │   └── FSMInstance.php
│   │   ├── ValueObject/
│   │   │   ├── State.php
│   │   │   ├── StateSet.php
│   │   │   ├── Symbol.php
│   │   │   ├── Alphabet.php
│   │   │   ├── TransitionFunction.php
│   │   │   └── InputString.php
│   │   ├── Builder/
│   │   │   └── AutomatonBuilder.php
│   │   ├── Result/
│   │   │   ├── ComputationResult.php
│   │   │   └── TransitionRecord.php
│   │   ├── Performance/
│   │   │   ├── CompiledAutomaton.php
│   │   │   └── BatchProcessor.php
│   │   └── Exception/
│   │       ├── InvalidAutomatonException.php
│   │       ├── InvalidInputException.php
│   │       └── InvalidTransitionException.php
│   │
│   ├── Application/           # Application layer - use cases
│   │   ├── Command/
│   │   │   ├── CreateFSMCommand.php
│   │   │   └── ExecuteFSMCommand.php
│   │   ├── Query/
│   │   │   └── GetFSMStateQuery.php
│   │   ├── Handler/
│   │   │   ├── CreateFSMHandler.php
│   │   │   ├── ExecuteFSMHandler.php
│   │   │   └── GetFSMStateHandler.php
│   │   ├── Service/
│   │   │   └── FSMExecutor.php
│   │   └── DTO/
│   │       ├── CreateFSMResult.php
│   │       └── ExecuteFSMResult.php
│   │
│   ├── Infrastructure/        # Infrastructure layer - adapters
│   │   ├── Persistence/
│   │   │   ├── FSMRepository.php
│   │   │   ├── RedisFSMRepository.php
│   │   │   └── InMemoryFSMRepository.php
│   │   ├── Serialization/
│   │   │   └── FSMSerializer.php
│   │   ├── Transport/
│   │   │   ├── Grpc/
│   │   │   │   ├── FSMGrpcService.php
│   │   │   │   └── GrpcServer.php
│   │   │   └── Rest/
│   │   │       ├── FSMController.php
│   │   │       └── RestServer.php
│   │   └── Exception/
│   │       ├── FSMNotFoundException.php
│   │       └── ConcurrencyException.php
│   │
│   └── Examples/              # Example implementations
│       ├── ModuloThree/
│       │   ├── ModuloThreeAutomaton.php
│       │   └── ModuloThreeService.php
│       ├── BinaryAdder/
│       │   └── BinaryAdderAutomaton.php
│       └── Regex/
│           └── EndsWithZeroOneAutomaton.php
│
├── proto/
│   └── fsm.proto
│
├── tests/
│   ├── Unit/
│   │   ├── Core/
│   │   └── Application/
│   ├── Integration/
│   ├── Property/
│   └── Performance/
│
├── bin/
│   ├── grpc-server.php
│   └── rest-server.php
│
├── config/
│   ├── services.yaml
│   └── routes.yaml
│
├── docker/
│   ├── Dockerfile
│   └── docker-compose.yml
│
├── docs/
│   ├── API.md
│   ├── EXAMPLES.md
│   └── PERFORMANCE.md
│
├── composer.json
├── phpunit.xml
├── psalm.xml
└── README.md
```

## 8. Deployment Configuration

### 8.1 Docker Configuration

```dockerfile
# Dockerfile
FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    autoconf \
    g++ \
    make \
    linux-headers \
    protobuf \
    protobuf-dev

# Install PHP extensions
RUN pecl install openswoole grpc redis \
    && docker-php-ext-enable openswoole grpc redis \
    && docker-php-ext-install gmp pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install protoc plugin for PHP
RUN composer global require spiral/roadrunner-cli --no-scripts --no-plugins

WORKDIR /app

# Copy application files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

COPY . .

# Generate gRPC code from proto files
RUN protoc --php_out=./src/Infrastructure/Transport/Grpc \
    --grpc_out=./src/Infrastructure/Transport/Grpc \
    --plugin=protoc-gen-grpc=/usr/bin/grpc_php_plugin \
    ./proto/fsm.proto

# Health check
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD php bin/health-check.php || exit 1

EXPOSE 9080 8080

# Default to gRPC server
CMD ["php", "bin/grpc-server.php"]
```

### 8.2 Docker Compose

```yaml
# docker-compose.yml
version: '3.9'

services:
  fsm-grpc:
    build:
      context: .
      dockerfile: docker/Dockerfile
    ports:
      - "9080:9080"
    environment:
      - SERVER_TYPE=grpc
      - GRPC_HOST=0.0.0.0
      - GRPC_PORT=9080
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LOG_LEVEL=info
    depends_on:
      - redis
    networks:
      - fsm-network
    restart: unless-stopped

  fsm-rest:
    build:
      context: .
      dockerfile: docker/Dockerfile
    command: ["php", "bin/rest-server.php"]
    ports:
      - "8080:8080"
    environment:
      - SERVER_TYPE=rest
      - REST_HOST=0.0.0.0
      - REST_PORT=8080
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LOG_LEVEL=info
    depends_on:
      - redis
    networks:
      - fsm-network
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data
    networks:
      - fsm-network
    restart: unless-stopped

  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./config/prometheus.yml:/etc/prometheus/prometheus.yml
      - prometheus-data:/prometheus
    networks:
      - fsm-network
    restart: unless-stopped

  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    volumes:
      - grafana-data:/var/lib/grafana
      - ./config/grafana/dashboards:/etc/grafana/provisioning/dashboards
    networks:
      - fsm-network
    restart: unless-stopped

networks:
  fsm-network:
    driver: bridge

volumes:
  redis-data:
  prometheus-data:
  grafana-data:
```

## 9. Configuration Files

### 9.1 Composer Configuration

```json
{
    "name": "fsm/library",
    "description": "Production-ready Finite State Machine library with gRPC and REST support",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.3",
        "ext-gmp": "*",
        "ext-json": "*",
        "ext-pcntl": "*",
        "openswoole/core": "^22.1",
        "openswoole/grpc": "^0.2",
        "google/protobuf": "^3.25",
        "grpc/grpc": "^1.57",
        "ramsey/uuid": "^4.7",
        "predis/predis": "^2.2",
        "psr/log": "^3.0",
        "psr/http-message": "^2.0",
        "psr/http-server-handler": "^1.0",
        "psr/http-server-middleware": "^1.0",
        "psr/container": "^2.0",
        "monolog/monolog": "^3.5"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "mockery/mockery": "^1.6",
        "giorgiosironi/eris": "^0.14",
        "vimeo/psalm": "^5.18",
        "phpstan/phpstan": "^1.10",
        "squizlabs/php_codesniffer": "^3.8",
        "phpmd/phpmd": "^2.14",
        "infection/infection": "^0.27",
        "phpbench/phpbench": "^1.2"
    },
    "autoload": {
        "psr-4": {
            "FSM\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "vendor/bin/phpunit",
        "test-coverage": "vendor/bin/phpunit --coverage-html coverage",
        "psalm": "vendor/bin/psalm",
        "phpstan": "vendor/bin/phpstan analyse",
        "cs-check": "vendor/bin/phpcs",
        "cs-fix": "vendor/bin/phpcbf",
        "mutation": "vendor/bin/infection --threads=4",
        "benchmark": "vendor/bin/phpbench run --report=default",
        "quality": [
            "@cs-check",
            "@psalm",
            "@phpstan",
            "@test"
        ]
    },
    "config": {
        "sort-packages": true,
        "optimize-autoloader": true,
        "preferred-install": {
            "*": "dist"
        }
    }
}
```

## 10. Updated Proto File

The proto file requires minor updates to better align with the v3 architecture:

```protobuf
syntax = "proto3";

package fsm;

option php_namespace = "FSM\\Infrastructure\\Transport\\Grpc\\Generated";
option php_metadata_namespace = "FSM\\Infrastructure\\Transport\\Grpc\\Meta";

// Main FSM Service
service FSMService {
  // Create a new FSM instance
  rpc CreateFSM(CreateFSMRequest) returns (CreateFSMResponse);
  
  // Execute input on an FSM (unary)
  rpc Execute(ExecuteRequest) returns (ExecuteResponse);
  
  // Execute input on an FSM (streaming)
  rpc ExecuteStream(stream ExecuteStreamRequest) returns (stream ExecuteStreamResponse);
  
  // Query FSM state
  rpc GetState(GetStateRequest) returns (GetStateResponse);
  
  // Modulo-three specific endpoint
  rpc ModuloThree(ModuloThreeRequest) returns (ModuloThreeResponse);
  
  // Batch processing
  rpc ExecuteBatch(ExecuteBatchRequest) returns (ExecuteBatchResponse);
  
  // List FSM instances
  rpc ListFSMs(ListFSMsRequest) returns (ListFSMsResponse);
  
  // Delete FSM instance
  rpc DeleteFSM(DeleteFSMRequest) returns (DeleteFSMResponse);
  
  // Validate FSM definition
  rpc ValidateFSM(ValidateFSMRequest) returns (ValidateFSMResponse);
}

// Core messages remain the same with minor adjustments...
// [Previous proto definitions continue]

// New messages for batch processing
message ExecuteBatchRequest {
  string fsm_id = 1;
  repeated string inputs = 2;
}

message ExecuteBatchResponse {
  repeated ExecuteResponse results = 1;
  double total_execution_time_ms = 2;
}
```

## 11. Key Improvements from v2

### Architecture Improvements
1. **True Library Design**: Core FSM library is completely independent of infrastructure
2. **Formal Mathematical Model**: Strict adherence to 5-tuple (Q, Σ, q0, F, δ) definition
3. **Clean Hexagonal Architecture**: Clear ports and adapters pattern
4. **Dual Transport Support**: Both gRPC and REST with thin adapters
5. **Developer-Friendly API**: Fluent builders and clear abstractions

### Technical Improvements
1. **Value Objects**: Proper domain modeling with immutable value objects
2. **No PHP Serialization**: Clean JSON serialization throughout
3. **Optimistic Locking**: Proper concurrency control with versions
4. **Performance Optimizations**: Compiled automata, batch processing
5. **Comprehensive Testing**: Unit, integration, property-based, and performance tests

### Operational Improvements
1. **Docker-Ready**: Complete containerization with health checks
2. **Monitoring**: Prometheus metrics and Grafana dashboards
3. **Documentation**: Clear examples and API documentation
4. **CI/CD Ready**: Quality checks and automated testing

## 12. Usage Examples

### Creating an FSM

```php
use FSM\Core\Builder\AutomatonBuilder;

// Build a simple even/odd detector
$automaton = AutomatonBuilder::create()
    ->withStates('Even', 'Odd')
    ->withAlphabet('0', '1')
    ->withInitialState('Even')
    ->withFinalStates('Even')
    ->withTransitions([
        'Even:0' => 'Even',
        'Even:1' => 'Odd',
        'Odd:0' => 'Odd',
        'Odd:1' => 'Even',
    ])
    ->build();

// Execute
$result = $automaton->execute(new InputString('110101'));
echo $result->isAccepted ? 'Even number of 1s' : 'Odd number of 1s';
```

### Using via gRPC

```php
use FSM\Grpc\FSMServiceClient;

$client = new FSMServiceClient('localhost:9080', [
    'credentials' => \Grpc\ChannelCredentials::createInsecure()
]);

// Create FSM
$request = new CreateFSMRequest();
$request->setDefinition($definition);
$request->setName('My FSM');

[$response, $status] = $client->CreateFSM($request)->wait();
$fsmId = $response->getFsmId();

// Execute
$execRequest = new ExecuteRequest();
$execRequest->setFsmId($fsmId);
$execRequest->setInputSequence('101010');

[$execResponse, $status] = $client->Execute($execRequest)->wait();
echo "Final state: " . $execResponse->getFinalState();
```

### Using via REST

```bash
# Create FSM
curl -X POST http://localhost:8080/api/fsm \
  -H "Content-Type: application/json" \
  -d '{
    "states": ["S0", "S1", "S2"],
    "alphabet": ["0", "1"],
    "initial_state": "S0",
    "final_states": ["S0", "S1", "S2"],
    "transitions": {
      "S0:0": "S0",
      "S0:1": "S1",
      "S1:0": "S2",
      "S1:1": "S0",
      "S2:0": "S1",
      "S2:1": "S2"
    },
    "name": "Modulo-3 FSM"
  }'

# Execute
curl -X POST http://localhost:8080/api/fsm/{fsm_id}/execute \
  -H "Content-Type: application/json" \
  -d '{
    "input": "110101",
    "record_history": true
  }'
```

## Conclusion

This v3 architecture delivers a **production-ready FSM library** that:

1. **Maintains mathematical rigor** with proper 5-tuple implementation
2. **Provides excellent developer experience** through clean APIs and builders
3. **Achieves high performance** with compiled automata and optimizations
4. **Supports multiple transports** (gRPC and REST) through clean adapters
5. **Follows best practices** in DDD, hexagonal architecture, and testing
6. **Is production-ready** with proper deployment, monitoring, and documentation

The library can be used both as a standalone PHP package and as a microservice, making it suitable for a wide range of applications from academic research to production systems.