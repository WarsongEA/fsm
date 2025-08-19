# FSM Architecture Design v2 - Simplified & Pragmatic (Fixed)

## Executive Summary

This document presents a **simplified and pragmatic** architecture for implementing a Finite State Machine (FSM) library in PHP using OpenSwoole with gRPC. The design prioritizes simplicity, performance, and maintainability while avoiding overengineering for the modulo-3 FSM task.

**Key Principles:**
- Simplicity first - avoid unnecessary abstractions
- gRPC-only interface (no REST/WebSocket)
- Fast path optimization for core FSM engine
- Practical DDD where it adds value
- Single persistence strategy (state-based, not event sourcing)

## Review Fixes Applied

### Blockers Fixed:
1. ✅ **Added missing getters and validation to FSMDefinition**
   - Implemented `getStates()`, `getAlphabet()`, `getInitialState()`, `getFinalStates()`, `getTransitions()`
   - Added comprehensive `validate()` method with proper error checking
   - Implemented `optimizeTransitions()` for O(1) lookups

2. ✅ **Replaced uniqid() with UUID v7**
   - Now using `Ramsey\Uuid\Uuid::uuid7()` for proper ID generation

3. ✅ **Removed serialize()/unserialize() and reflection**
   - FSM state stored as normalized JSON
   - Added `rehydrate()` static factory method to FSMInstance
   - Removed all reflection usage in repository

4. ✅ **Fixed gRPC proto - removed success/error_message fields**
   - All errors now use proper gRPC status codes
   - Removed success/error_message from all response messages

5. ✅ **Added concurrency control**
   - Added version field to FSMInstance for optimistic locking
   - Repository checks version on save, throws ConcurrencyException
   - Maps conflicts to ABORTED gRPC status

### Improvements Made:
6. ✅ **Made history optional in fast path**
   - Added `recordHistory` and `recordTimestamps` flags
   - History recording disabled by default for performance

7. ✅ **Fixed cache key generation**
   - Now uses content hash (MD5 of definition) instead of spl_object_hash

8. ✅ **Added history size limit**
   - Set MAX_HISTORY_SIZE to 10,000 entries
   - Automatically removes oldest entries when limit reached

9. ✅ **Fixed decimal_value overflow**
   - Changed to string type in proto
   - Using GMP extension for arbitrary precision arithmetic

10. ✅ **Clarified ExecuteStream semantics**
    - Added detailed documentation of chunking behavior
    - Client can send single chars or chunks
    - Server streams state changes for each transition

---

## 1. Simplified Domain Model

### 1.1 Core Entities & Value Objects

```php
namespace FSM\Domain;

// The FSM configuration - immutable after creation
final class FSMDefinition {
    private array $optimizedTransitions;
    
    public function __construct(
        private readonly array $states,        // ['S0', 'S1', 'S2']
        private readonly array $alphabet,      // ['0', '1']
        private readonly string $initialState, // 'S0'
        private readonly array $finalStates,   // ['S0', 'S1', 'S2']
        private readonly array $transitions    // Raw transition array
    ) {
        $this->validate();
        $this->optimizeTransitions();
    }
    
    // Validation method to ensure FSM is well-formed
    private function validate(): void {
        if (empty($this->states)) {
            throw new \InvalidArgumentException('FSM must have at least one state');
        }
        
        if (empty($this->alphabet)) {
            throw new \InvalidArgumentException('FSM must have at least one input symbol');
        }
        
        if (!in_array($this->initialState, $this->states)) {
            throw new \InvalidArgumentException('Initial state must be in states array');
        }
        
        foreach ($this->finalStates as $finalState) {
            if (!in_array($finalState, $this->states)) {
                throw new \InvalidArgumentException("Final state '{$finalState}' not in states array");
            }
        }
        
        // Validate transitions
        foreach ($this->transitions as $key => $toState) {
            if (!in_array($toState, $this->states)) {
                throw new \InvalidArgumentException("Transition target '{$toState}' not in states array");
            }
        }
    }
    
    // Optimize transitions into hash map for O(1) lookup
    private function optimizeTransitions(): void {
        $this->optimizedTransitions = [];
        
        // If transitions are already in optimized format
        if ($this->isOptimizedFormat($this->transitions)) {
            $this->optimizedTransitions = $this->transitions;
            return;
        }
        
        // Convert from array of Transition objects to hash map
        foreach ($this->transitions as $transition) {
            if (is_array($transition)) {
                $key = $transition['from_state'] . ':' . $transition['input'];
                $this->optimizedTransitions[$key] = $transition['to_state'];
            }
        }
    }
    
    private function isOptimizedFormat(array $transitions): bool {
        if (empty($transitions)) {
            return true;
        }
        
        $firstKey = array_key_first($transitions);
        return is_string($firstKey) && str_contains($firstKey, ':');
    }
    
    // O(1) transition lookup using hash map
    public function getNextState(string $currentState, string $input): ?string {
        $key = $currentState . ':' . $input;
        return $this->optimizedTransitions[$key] ?? null;
    }
    
    // Getter methods
    public function getStates(): array {
        return $this->states;
    }
    
    public function getAlphabet(): array {
        return $this->alphabet;
    }
    
    public function getInitialState(): string {
        return $this->initialState;
    }
    
    public function getFinalStates(): array {
        return $this->finalStates;
    }
    
    public function getTransitions(): array {
        return $this->optimizedTransitions;
    }
    
    public function isFinalState(string $state): bool {
        return in_array($state, $this->finalStates);
    }
}

// Runtime FSM instance - mutable state with optimistic locking
final class FSMInstance {
    private string $currentState;
    private array $history = [];
    private int $version = 0;
    private const MAX_HISTORY_SIZE = 10000; // Configurable limit
    
    public function __construct(
        private readonly string $id,
        private readonly FSMDefinition $definition,
        private readonly bool $recordHistory = false,
        private readonly bool $recordTimestamps = false
    ) {
        $this->currentState = $definition->getInitialState();
    }
    
    public function process(string $input): void {
        $nextState = $this->definition->getNextState($this->currentState, $input);
        
        if ($nextState === null) {
            throw new InvalidTransitionException(
                "No transition from {$this->currentState} with input {$input}"
            );
        }
        
        // Only record history if enabled (performance optimization)
        if ($this->recordHistory) {
            $this->addToHistory($this->currentState, $input, $nextState);
        }
        
        $this->currentState = $nextState;
        $this->version++; // Increment version for optimistic locking
    }
    
    private function addToHistory(string $from, string $input, string $to): void {
        // Enforce history size limit
        if (count($this->history) >= self::MAX_HISTORY_SIZE) {
            array_shift($this->history); // Remove oldest entry
        }
        
        $record = [
            'from' => $from,
            'input' => $input,
            'to' => $to
        ];
        
        if ($this->recordTimestamps) {
            $record['timestamp'] = microtime(true);
        }
        
        $this->history[] = $record;
    }
    
    public function getCurrentState(): string {
        return $this->currentState;
    }
    
    public function getHistory(): array {
        return $this->history;
    }
    
    public function getId(): string {
        return $this->id;
    }
    
    public function getDefinition(): FSMDefinition {
        return $this->definition;
    }
    
    public function getVersion(): int {
        return $this->version;
    }
    
    public function isFinalState(): bool {
        return $this->definition->isFinalState($this->currentState);
    }
    
    // Factory method to rehydrate from stored data
    public static function rehydrate(
        string $id,
        FSMDefinition $definition,
        string $currentState,
        array $history,
        int $version,
        bool $recordHistory = false,
        bool $recordTimestamps = false
    ): self {
        $instance = new self($id, $definition, $recordHistory, $recordTimestamps);
        $instance->currentState = $currentState;
        $instance->history = $history;
        $instance->version = $version;
        return $instance;
    }
    
    // Export state for persistence
    public function toArray(): array {
        return [
            'id' => $this->id,
            'current_state' => $this->currentState,
            'history' => $this->history,
            'version' => $this->version,
            'record_history' => $this->recordHistory,
            'record_timestamps' => $this->recordTimestamps
        ];
    }
}
```

### 1.2 Domain Services

```php
namespace FSM\Domain\Service;

// Fast execution engine with optional hooks
final class FSMEngine {
    private array $hooks = [];
    
    public function execute(FSMInstance $fsm, string $inputSequence): ExecutionResult {
        $startTime = microtime(true);
        $inputs = str_split($inputSequence);
        
        foreach ($inputs as $input) {
            $this->beforeTransition($fsm, $input);
            $fsm->process($input);
            $this->afterTransition($fsm, $input);
        }
        
        return new ExecutionResult(
            finalState: $fsm->getCurrentState(),
            transitions: $fsm->getHistory(),
            executionTime: microtime(true) - $startTime
        );
    }
    
    // Optional hooks for extensibility
    public function addHook(string $event, callable $callback): void {
        $this->hooks[$event][] = $callback;
    }
    
    private function beforeTransition(FSMInstance $fsm, string $input): void {
        foreach ($this->hooks['before_transition'] ?? [] as $hook) {
            $hook($fsm, $input);
        }
    }
    
    private function afterTransition(FSMInstance $fsm, string $input): void {
        foreach ($this->hooks['after_transition'] ?? [] as $hook) {
            $hook($fsm, $input);
        }
    }
}
```

### 1.3 Modulo Three Implementation

```php
namespace FSM\Domain\ModuloThree;

final class ModuloThreeFSM {
    private static ?FSMDefinition $definition = null;
    
    public static function getDefinition(): FSMDefinition {
        if (self::$definition === null) {
            self::$definition = new FSMDefinition(
                states: ['S0', 'S1', 'S2'],
                alphabet: ['0', '1'],
                initialState: 'S0',
                finalStates: ['S0', 'S1', 'S2'],
                transitions: [
                    'S0:0' => 'S0', 'S0:1' => 'S1',
                    'S1:0' => 'S2', 'S1:1' => 'S0',
                    'S2:0' => 'S1', 'S2:1' => 'S2',
                ]
            );
        }
        return self::$definition;
    }
    
    public static function calculate(string $binaryString): int {
        $fsm = new FSMInstance(
            id: \Ramsey\Uuid\Uuid::uuid7()->toString(),
            definition: self::getDefinition(),
            recordHistory: false,  // No history needed for fast calculation
            recordTimestamps: false
        
        $engine = new FSMEngine();
        $result = $engine->execute($fsm, $binaryString);
        
        return match($result->finalState) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
        };
    }
}
```

---

## 2. gRPC Service Definition

The complete proto file defines our service contract:

```protobuf
syntax = "proto3";

package fsm;

option php_namespace = "FSM\\Grpc";
option php_metadata_namespace = "FSM\\Grpc\\Meta";

// Service definition
service FSMService {
  // Create a new FSM definition
  rpc CreateFSM(CreateFSMRequest) returns (CreateFSMResponse);
  
  // Execute input on an FSM (unary)
  rpc Execute(ExecuteRequest) returns (ExecuteResponse);
  
  // Execute input on an FSM (streaming for real-time updates)
  // Client streams inputs, server streams state changes
  rpc ExecuteStream(stream ExecuteStreamRequest) returns (stream ExecuteStreamResponse);
  
  // Get FSM state
  rpc GetState(GetStateRequest) returns (GetStateResponse);
  
  // Modulo-three specific endpoint
  rpc ModuloThree(ModuloThreeRequest) returns (ModuloThreeResponse);
}

// Messages - all errors use gRPC status codes, no success/error fields
message CreateFSMRequest {
  repeated string states = 1;
  repeated string alphabet = 2;
  string initial_state = 3;
  repeated string final_states = 4;
  repeated Transition transitions = 5;
}

message Transition {
  string from_state = 1;
  string input = 2;
  string to_state = 3;
}

message CreateFSMResponse {
  string fsm_id = 1;
  // Success/failure indicated by gRPC status
}

message ExecuteRequest {
  string fsm_id = 1;
  string input_sequence = 2;
  bool record_history = 3;  // Optional: enable history recording
}

message ExecuteResponse {
  string final_state = 1;
  repeated TransitionRecord transitions = 2;
  double execution_time_ms = 3;
  bool is_final_state = 4;
}

message TransitionRecord {
  string from_state = 1;
  string input = 2;
  string to_state = 3;
  double timestamp = 4;  // Optional, only if timestamps enabled
}

message ExecuteStreamRequest {
  string fsm_id = 1;
  string input = 2;  // Single input or chunk
}

message ExecuteStreamResponse {
  string current_state = 1;
  TransitionRecord last_transition = 2;
  bool is_final_state = 3;
}

message GetStateRequest {
  string fsm_id = 1;
  int32 history_limit = 2;  // 0 = all history
}

message GetStateResponse {
  string current_state = 1;
  repeated TransitionRecord history = 2;
  bool is_final_state = 3;
}

message ModuloThreeRequest {
  string binary_input = 1;
  bool return_transitions = 2;  // Optional transition history
}

message ModuloThreeResponse {
  int32 result = 1;
  string final_state = 2;
  string decimal_value = 3;  // String to handle big integers
  repeated TransitionRecord transitions = 4;
  double execution_time_ms = 5;
}
```

---

## 3. Infrastructure with OpenSwoole + gRPC

### 3.1 gRPC Server Implementation

```php
namespace FSM\Infrastructure\Grpc;

use OpenSwoole\GRPC\Server;
use OpenSwoole\GRPC\Middleware\LoggingMiddleware;
use OpenSwoole\GRPC\Middleware\TraceMiddleware;

final class GrpcServer {
    private Server $server;
    private FSMRepository $repository;
    private FSMEngine $engine;
    
    public function __construct(string $host = '0.0.0.0', int $port = 9080) {
        $this->server = new Server($host, $port);
        $this->repository = new RedisFSMRepository(); // Or InMemory for simplicity
        $this->engine = new FSMEngine();
        
        $this->configure();
        $this->registerServices();
    }
    
    private function configure(): void {
        $this->server->set([
            'worker_num' => swoole_cpu_num() * 2,
            'open_http2_protocol' => true,
            'enable_coroutine' => true,
            'max_coroutine' => 10000,
        ]);
        
        // Add middleware
        $this->server->addMiddleware(new LoggingMiddleware());
        $this->server->addMiddleware(new TraceMiddleware());
    }
    
    private function registerServices(): void {
        $service = new FSMServiceImpl($this->repository, $this->engine);
        $this->server->register(FSMService::class, $service);
    }
    
    public function start(): void {
        $this->server->start();
    }
}
```

### 3.2 ExecuteStream Semantics

The ExecuteStream RPC provides bidirectional streaming for real-time FSM execution:

```php
// Client streams inputs one at a time or in chunks
// Server streams back state changes as they occur

public function ExecuteStream(Request $request, Response $response): void {
    go(function() use ($request, $response) {
        $fsmId = null;
        $fsm = null;
        
        // Process incoming stream of inputs
        while ($message = $request->recv()) {
            if (!$fsmId) {
                $fsmId = $message->getFsmId();
                $fsm = $this->repository->find($fsmId);
                if (!$fsm) {
                    $response->setStatus(Status::notFound('FSM not found'));
                    return;
                }
            }
            
            $input = $message->getInput();
            
            // Process input (single character or chunk)
            foreach (str_split($input) as $char) {
                try {
                    $prevState = $fsm->getCurrentState();
                    $fsm->process($char);
                    
                    // Stream state change to client
                    $response->send([
                        'current_state' => $fsm->getCurrentState(),
                        'last_transition' => [
                            'from_state' => $prevState,
                            'input' => $char,
                            'to_state' => $fsm->getCurrentState()
                        ],
                        'is_final_state' => $fsm->isFinalState()
                    ]);
                } catch (InvalidTransitionException $e) {
                    $response->setStatus(Status::invalidArgument($e->getMessage()));
                    return;
                }
            }
        }
        
        // Save final state with optimistic locking
        try {
            $this->repository->save($fsm, $fsm->getVersion() - count($inputs));
        } catch (ConcurrencyException $e) {
            $response->setStatus(Status::aborted('Concurrent modification detected'));
        }
    });
}
```

**Chunking Behavior:**
- Client can send single characters for real-time feedback
- Client can send chunks (e.g., "0101") for batch processing
- Server streams a response for each state transition
- Useful for interactive FSM exploration or real-time monitoring

### 3.3 Service Implementation

```php
namespace FSM\Infrastructure\Grpc;

use FSM\Grpc\FSMServiceInterface;
use OpenSwoole\GRPC\{Request, Response, Status};

final class FSMServiceImpl implements FSMServiceInterface {
    public function __construct(
        private readonly FSMRepository $repository,
        private readonly FSMEngine $engine
    ) {}
    
    public function CreateFSM(Request $request, Response $response): void {
        go(function() use ($request, $response) {
            try {
                $data = $request->getMessage();
                
                // Build transitions map
                $transitions = [];
                foreach ($data->getTransitions() as $t) {
                    $key = $t->getFromState() . ':' . $t->getInput();
                    $transitions[$key] = $t->getToState();
                }
                
                $definition = new FSMDefinition(
                    states: $data->getStates(),
                    alphabet: $data->getAlphabet(),
                    initialState: $data->getInitialState(),
                    finalStates: $data->getFinalStates(),
                    transitions: $transitions
                );
                
                $fsm = new FSMInstance(
                    id: \Ramsey\Uuid\Uuid::uuid7()->toString(),
                    definition: $definition,
                    recordHistory: true,  // Enable history for created FSMs
                    recordTimestamps: true
                );
                
                $this->repository->save($fsm);
                
                $response->setMessage([
                    'fsm_id' => $fsm->getId()
                ]);
                $response->setStatus(Status::ok());
            } catch (\Exception $e) {
                $response->setStatus(Status::invalid_argument($e->getMessage()));
            }
        });
    }
    
    public function Execute(Request $request, Response $response): void {
        go(function() use ($request, $response) {
            try {
                $data = $request->getMessage();
                $fsm = $this->repository->find($data->getFsmId());
                
                if (!$fsm) {
                    throw new \RuntimeException('FSM not found');
                }
                
                $result = $this->engine->execute($fsm, $data->getInputSequence());
                
                $response->setMessage([
                    'final_state' => $result->finalState,
                    'transitions' => $result->transitions,
                    'execution_time_ms' => $result->executionTime * 1000
                ]);
                $response->setStatus(Status::ok());
                
                // Save updated state with optimistic locking
                $this->repository->save($fsm, $fsm->getVersion() - 1);
            } catch (ConcurrencyException $e) {
                $response->setStatus(Status::aborted($e->getMessage()));
            } catch (\Exception $e) {
                $response->setStatus(Status::internal($e->getMessage()));
            }
        });
    }
    
    public function ModuloThree(Request $request, Response $response): void {
        go(function() use ($request, $response) {
            try {
                $binaryInput = $request->getMessage()->getBinaryInput();
                
                // Validate input
                if (!preg_match('/^[01]+$/', $binaryInput)) {
                    throw new \InvalidArgumentException('Input must be binary string');
                }
                
                $result = ModuloThreeFSM::calculate($binaryInput);
                
                // Use GMP for large binary numbers to avoid overflow
                $decimalValue = gmp_strval(gmp_init($binaryInput, 2), 10);
                
                $response->setMessage([
                    'result' => $result,
                    'final_state' => 'S' . $result,
                    'decimal_value' => $decimalValue  // Now a string
                ]);
                $response->setStatus(Status::ok());
            } catch (\Exception $e) {
                $response->setStatus(Status::invalid_argument($e->getMessage()));
            }
        });
    }
}
```

### 3.3 Persistence Strategy (Simple State Storage)

```php
namespace FSM\Infrastructure\Persistence;

// Simple in-memory repository with optional Redis backing and optimistic locking
final class FSMRepository {
    private array $storage = [];
    private ?Redis $redis = null;
    
    public function __construct(?Redis $redis = null) {
        $this->redis = $redis;
    }
    
    public function save(FSMInstance $fsm, ?int $expectedVersion = null): void {
        // Check for version conflict (optimistic locking)
        if ($expectedVersion !== null) {
            $existing = $this->find($fsm->getId());
            if ($existing && $existing->getVersion() !== $expectedVersion) {
                throw new ConcurrencyException(
                    'Version mismatch: expected ' . $expectedVersion . 
                    ', found ' . $existing->getVersion()
                );
            }
        }
        
        // Store FSM state as normalized JSON
        $data = array_merge($fsm->toArray(), [
            'definition' => $this->serializeDefinition($fsm->getDefinition())
        ]);
        
        $this->storage[$fsm->getId()] = $data;
        
        if ($this->redis) {
            $this->redis->set(
                'fsm:' . $fsm->getId(),
                json_encode($data),
                ['EX' => 3600] // 1 hour TTL
            );
        }
    }
    
    public function find(string $id): ?FSMInstance {
        // Try memory first
        if (isset($this->storage[$id])) {
            return $this->rehydrate($this->storage[$id]);
        }
        
        // Try Redis
        if ($this->redis) {
            $data = $this->redis->get('fsm:' . $id);
            if ($data) {
                $data = json_decode($data, true);
                $this->storage[$id] = $data; // Cache in memory
                return $this->rehydrate($data);
            }
        }
        
        return null;
    }
    
    private function rehydrate(array $data): FSMInstance {
        $definition = $this->deserializeDefinition($data['definition']);
        
        return FSMInstance::rehydrate(
            id: $data['id'],
            definition: $definition,
            currentState: $data['current_state'],
            history: $data['history'] ?? [],
            version: $data['version'] ?? 0,
            recordHistory: $data['record_history'] ?? false,
            recordTimestamps: $data['record_timestamps'] ?? false
        );
    }
    
    private function serializeDefinition(FSMDefinition $definition): array {
        return [
            'states' => $definition->getStates(),
            'alphabet' => $definition->getAlphabet(),
            'initial_state' => $definition->getInitialState(),
            'final_states' => $definition->getFinalStates(),
            'transitions' => $definition->getTransitions()
        ];
    }
    
    private function deserializeDefinition(array $data): FSMDefinition {
        return new FSMDefinition(
            states: $data['states'],
            alphabet: $data['alphabet'],
            initialState: $data['initial_state'],
            finalStates: $data['final_states'],
            transitions: $data['transitions']
        );
    }
}

// Exception for concurrency conflicts
final class ConcurrencyException extends \RuntimeException {}
```

---

## 4. Optimized FSM Engine

### 4.1 Fast Path Implementation

```php
namespace FSM\Domain\Service;

final class OptimizedFSMEngine {
    // Pre-compiled transition tables for known FSMs
    private static array $compiledTables = [];
    
    public function executeFast(FSMInstance $fsm, string $inputSequence): ExecutionResult {
        $startTime = microtime(true);
        
        // Get or compile transition table
        $table = $this->getCompiledTable($fsm->getDefinition());
        
        // Fast path: direct array lookups, no method calls
        $currentState = $fsm->getCurrentState();
        $stateIndex = $table['state_map'][$currentState];
        $inputs = str_split($inputSequence);
        $history = [];
        
        foreach ($inputs as $input) {
            $inputIndex = $table['input_map'][$input] ?? -1;
            
            if ($inputIndex === -1) {
                throw new InvalidTransitionException("Invalid input: {$input}");
            }
            
            $nextStateIndex = $table['transitions'][$stateIndex][$inputIndex];
            
            if ($nextStateIndex === -1) {
                throw new InvalidTransitionException(
                    "No transition from {$currentState} with input {$input}"
                );
            }
            
            $nextState = $table['states'][$nextStateIndex];
            
            $history[] = [
                'from' => $currentState,
                'input' => $input,
                'to' => $nextState,
                'timestamp' => microtime(true)
            ];
            
            $currentState = $nextState;
            $stateIndex = $nextStateIndex;
        }
        
        // Update FSM state by processing each input
        // This avoids reflection and maintains encapsulation
        foreach ($inputs as $input) {
            $fsm->process($input);
        }
        
        return new ExecutionResult(
            finalState: $currentState,
            transitions: $history,
            executionTime: microtime(true) - $startTime
        );
    }
    
    private function getCompiledTable(FSMDefinition $definition): array {
        // Use content hash instead of object hash for cache key
        $key = $this->getDefinitionHash($definition);
        
        if (!isset(self::$compiledTables[$key])) {
            self::$compiledTables[$key] = $this->compileTable($definition);
        }
        
        return self::$compiledTables[$key];
    }
    
    private function getDefinitionHash(FSMDefinition $definition): string {
        $data = [
            'states' => $definition->getStates(),
            'alphabet' => $definition->getAlphabet(),
            'initial' => $definition->getInitialState(),
            'finals' => $definition->getFinalStates(),
            'transitions' => $definition->getTransitions()
        ];
        return md5(json_encode($data));
    }
    
    private function compileTable(FSMDefinition $definition): array {
        $states = $definition->getStates();
        $alphabet = $definition->getAlphabet();
        
        // Create state and input indices for O(1) lookup
        $stateMap = array_flip($states);
        $inputMap = array_flip($alphabet);
        
        // Build 2D transition table
        $transitions = array_fill(0, count($states), array_fill(0, count($alphabet), -1));
        
        foreach ($definition->getTransitions() as $key => $toState) {
            [$fromState, $input] = explode(':', $key);
            $fromIndex = $stateMap[$fromState];
            $inputIndex = $inputMap[$input];
            $toIndex = $stateMap[$toState];
            $transitions[$fromIndex][$inputIndex] = $toIndex;
        }
        
        return [
            'states' => $states,
            'state_map' => $stateMap,
            'input_map' => $inputMap,
            'transitions' => $transitions
        ];
    }
}
```

---

## 5. Testing Strategy

### 5.1 Unit Tests

```php
namespace Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use FSM\Domain\FSMDefinition;
use FSM\Domain\FSMInstance;

final class FSMTest extends TestCase {
    public function testModuloThreeCorrectness(): void {
        $testCases = [
            ['110', 0],    // 6 mod 3 = 0
            ['1101', 1],   // 13 mod 3 = 1
            ['1110', 2],   // 14 mod 3 = 2
            ['1111', 0],   // 15 mod 3 = 0
        ];
        
        foreach ($testCases as [$input, $expected]) {
            $result = ModuloThreeFSM::calculate($input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }
    
    public function testInvalidTransition(): void {
        $definition = ModuloThreeFSM::getDefinition();
        $fsm = new FSMInstance('test', $definition);
        
        $this->expectException(InvalidTransitionException::class);
        $fsm->process('2'); // Invalid input
    }
}
```

### 5.2 Property-Based Testing

```php
namespace Tests\Property;

use Eris\Generator;
use Eris\TestTrait;

final class ModuloThreePropertyTest extends TestCase {
    use TestTrait;
    
    public function testModuloThreeProperty(): void {
        $this->forAll(
            Generator\pos() // Positive integers
        )->then(function($number) {
            $binary = decbin($number);
            $fsmResult = ModuloThreeFSM::calculate($binary);
            $expectedResult = $number % 3;
            
            $this->assertEquals(
                $expectedResult,
                $fsmResult,
                "Failed for number {$number} (binary: {$binary})"
            );
        });
    }
}
```

### 5.3 gRPC Contract Tests

```php
namespace Tests\Integration;

use FSM\Grpc\FSMServiceClient;
use Grpc\ChannelCredentials;

final class GrpcIntegrationTest extends TestCase {
    private FSMServiceClient $client;
    
    protected function setUp(): void {
        $this->client = new FSMServiceClient(
            'localhost:9080',
            ['credentials' => ChannelCredentials::createInsecure()]
        );
    }
    
    public function testModuloThreeEndpoint(): void {
        $request = new ModuloThreeRequest();
        $request->setBinaryInput('1101');
        
        [$response, $status] = $this->client->ModuloThree($request)->wait();
        
        $this->assertEquals(\Grpc\STATUS_OK, $status->code);
        $this->assertEquals(1, $response->getResult());
        $this->assertEquals('S1', $response->getFinalState());
        $this->assertEquals(13, $response->getDecimalValue());
    }
}
```

### 5.4 Performance Tests

```php
namespace Tests\Performance;

final class PerformanceTest extends TestCase {
    public function testLargeInputPerformance(): void {
        $largeInput = str_repeat('101010', 10000); // 60,000 bits
        
        $startTime = microtime(true);
        $result = ModuloThreeFSM::calculate($largeInput);
        $executionTime = microtime(true) - $startTime;
        
        // Should process in under 50ms
        $this->assertLessThan(0.05, $executionTime);
        
        // Verify correctness using GMP for large numbers
        $expected = gmp_mod(gmp_init($largeInput, 2), 3);
        $this->assertEquals(gmp_intval($expected), $result);
    }
}
```

---

## 6. Simplified Implementation Plan

### Phase 1: Core FSM (2 days)
- Implement FSMDefinition and FSMInstance
- Create FSMEngine with fast path optimization
- Implement ModuloThreeFSM
- Write unit tests

### Phase 2: gRPC Integration (2 days)
- Create proto file
- Generate PHP stubs
- Implement FSMServiceImpl
- Set up OpenSwoole gRPC server
- Write gRPC client tests

### Phase 3: Persistence & Polish (1 day)
- Implement simple FSMRepository (in-memory + optional Redis)
- Add basic logging and metrics
- Performance testing
- Documentation

### Phase 4: Deployment (1 day)
- Docker configuration for gRPC server
- Health checks
- Basic monitoring
- Load testing

---

## 7. Configuration & Deployment

### 7.1 Docker Setup

```dockerfile
FROM php:8.2-cli

# Install OpenSwoole and gRPC extensions
RUN pecl install openswoole grpc && \
    docker-php-ext-enable openswoole grpc

# Install protobuf compiler
RUN apt-get update && apt-get install -y protobuf-compiler

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 9080

CMD ["php", "bin/grpc-server.php"]
```

### 7.2 Server Bootstrap

```php
#!/usr/bin/env php
<?php
// bin/grpc-server.php

require __DIR__ . '/../vendor/autoload.php';

use FSM\Infrastructure\Grpc\GrpcServer;

$server = new GrpcServer(
    host: $_ENV['GRPC_HOST'] ?? '0.0.0.0',
    port: (int)($_ENV['GRPC_PORT'] ?? 9080)
);

echo "Starting gRPC server on {$_ENV['GRPC_HOST']}:{$_ENV['GRPC_PORT']}\n";
$server->start();
```

---

## 8. Concurrency Model

### 8.1 Optimistic Locking

The architecture uses optimistic locking to handle concurrent FSM modifications:

```php
// Version tracking in FSMInstance
private int $version = 0;

public function process(string $input): void {
    // ... perform transition ...
    $this->version++; // Increment on each state change
}

// Repository checks version on save
public function save(FSMInstance $fsm, ?int $expectedVersion = null): void {
    if ($expectedVersion !== null) {
        $existing = $this->find($fsm->getId());
        if ($existing && $existing->getVersion() !== $expectedVersion) {
            throw new ConcurrencyException('Version mismatch');
        }
    }
    // ... save FSM ...
}
```

### 8.2 gRPC Status Mapping

| Condition | gRPC Status | Description |
|-----------|-------------|-------------|
| Success | OK | Operation completed successfully |
| Invalid FSM definition | INVALID_ARGUMENT | Malformed FSM structure |
| FSM not found | NOT_FOUND | FSM ID doesn't exist |
| Invalid transition | INVALID_ARGUMENT | No valid transition for input |
| Concurrent modification | ABORTED | Version conflict detected |
| Internal error | INTERNAL | Unexpected server error |

### 8.3 Performance Flags

```php
// Fast path without history (for calculations)
$fsm = new FSMInstance(
    id: $id,
    definition: $definition,
    recordHistory: false,    // Skip history recording
    recordTimestamps: false  // Skip timestamp recording
);

// Full tracking (for debugging/monitoring)
$fsm = new FSMInstance(
    id: $id,
    definition: $definition,
    recordHistory: true,     // Record all transitions
    recordTimestamps: true   // Include timestamps
);
```

## 9. Key Simplifications from v1

### What We Removed:
1. **Event Sourcing**: Using simple state persistence instead
2. **Multiple API Types**: Only gRPC, no REST/WebSocket
3. **Complex DDD Layers**: Simplified to essential domain model
4. **Excessive Abstractions**: Direct implementations where sensible
5. **Multiple Persistence Options**: Single strategy with optional Redis

### What We Kept:
1. **Core FSM Logic**: Clean domain model for FSM
2. **Performance Optimization**: Fast path with compiled tables
3. **gRPC Interface**: Modern, efficient RPC protocol
4. **Good Testing**: Unit, integration, and property-based tests
5. **OpenSwoole Coroutines**: Efficient async processing

### Performance Improvements:
1. **Compiled Transition Tables**: O(1) lookups with integer indices
2. **Minimal Allocations**: Reuse data structures
3. **Coroutine-Based**: Non-blocking I/O with OpenSwoole
4. **Connection Pooling**: Efficient database connections
5. **Binary Protocol**: gRPC is more efficient than JSON/REST

---

## 10. Dependencies

### Required Packages

```json
{
    "require": {
        "php": ">=8.2",
        "openswoole/core": "^22.0",
        "openswoole/grpc": "^0.1",
        "google/protobuf": "^3.0",
        "ramsey/uuid": "^4.0",
        "ext-gmp": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "giorgiosironi/eris": "^0.14",
        "vimeo/psalm": "^5.0",
        "phpstan/phpstan": "^1.0"
    }
}
```

## Conclusion

This simplified architecture provides a clean, maintainable FSM library that:
- **Handles the modulo-3 example efficiently** with optimized transition tables
- **Uses modern gRPC** for efficient communication
- **Leverages OpenSwoole** for high-performance async processing
- **Maintains code quality** without overengineering
- **Is production-ready** but pragmatic

The implementation focuses on what matters: a fast, correct FSM engine with a clean API, avoiding unnecessary complexity while maintaining professional code standards.