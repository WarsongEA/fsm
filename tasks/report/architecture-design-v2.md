# FSM Architecture Design v2 - Simplified & Pragmatic

## Executive Summary

This document presents a **simplified and pragmatic** architecture for implementing a Finite State Machine (FSM) library in PHP using OpenSwoole with gRPC. The design prioritizes simplicity, performance, and maintainability while avoiding overengineering for the modulo-3 FSM task.

**Key Principles:**
- Simplicity first - avoid unnecessary abstractions
- gRPC-only interface (no REST/WebSocket)
- Fast path optimization for core FSM engine
- Practical DDD where it adds value
- Single persistence strategy (state-based, not event sourcing)

---

## 1. Simplified Domain Model

### 1.1 Core Entities & Value Objects

```php
namespace FSM\Domain;

// The FSM configuration - immutable after creation
final class FSMDefinition {
    public function __construct(
        private readonly array $states,        // ['S0', 'S1', 'S2']
        private readonly array $alphabet,      // ['0', '1']
        private readonly string $initialState, // 'S0'
        private readonly array $finalStates,   // ['S0', 'S1', 'S2']
        private readonly array $transitions    // Optimized transition table
    ) {
        $this->validate();
        $this->optimizeTransitions();
    }
    
    // O(1) transition lookup using hash map
    public function getNextState(string $currentState, string $input): ?string {
        $key = $currentState . ':' . $input;
        return $this->transitions[$key] ?? null;
    }
}

// Runtime FSM instance - mutable state
final class FSMInstance {
    private string $currentState;
    private array $history = [];
    
    public function __construct(
        private readonly string $id,
        private readonly FSMDefinition $definition
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
        
        $this->history[] = [
            'from' => $this->currentState,
            'input' => $input,
            'to' => $nextState,
            'timestamp' => microtime(true)
        ];
        
        $this->currentState = $nextState;
    }
    
    public function getCurrentState(): string {
        return $this->currentState;
    }
    
    public function getHistory(): array {
        return $this->history;
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
            id: uniqid('mod3_'),
            definition: self::getDefinition()
        );
        
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
  rpc ExecuteStream(stream ExecuteStreamRequest) returns (stream ExecuteStreamResponse);
  
  // Get FSM state
  rpc GetState(GetStateRequest) returns (GetStateResponse);
  
  // Modulo-three specific endpoint
  rpc ModuloThree(ModuloThreeRequest) returns (ModuloThreeResponse);
}

// Messages
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
  bool success = 2;
  string error_message = 3;
}

message ExecuteRequest {
  string fsm_id = 1;
  string input_sequence = 2;
}

message ExecuteResponse {
  string final_state = 1;
  repeated TransitionRecord transitions = 2;
  double execution_time_ms = 3;
}

message TransitionRecord {
  string from_state = 1;
  string input = 2;
  string to_state = 3;
  double timestamp = 4;
}

message ExecuteStreamRequest {
  string fsm_id = 1;
  string input = 2;
}

message ExecuteStreamResponse {
  string current_state = 1;
  TransitionRecord last_transition = 2;
}

message GetStateRequest {
  string fsm_id = 1;
}

message GetStateResponse {
  string current_state = 1;
  repeated TransitionRecord history = 2;
}

message ModuloThreeRequest {
  string binary_input = 1;
}

message ModuloThreeResponse {
  int32 result = 1;
  string final_state = 2;
  int64 decimal_value = 3;
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

### 3.2 Service Implementation

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
                    id: uniqid('fsm_'),
                    definition: $definition
                );
                
                $this->repository->save($fsm);
                
                $response->setMessage([
                    'fsm_id' => $fsm->getId(),
                    'success' => true
                ]);
                $response->setStatus(Status::ok());
            } catch (\Exception $e) {
                $response->setMessage([
                    'success' => false,
                    'error_message' => $e->getMessage()
                ]);
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
                
                // Save updated state
                $this->repository->save($fsm);
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
                $decimalValue = bindec($binaryInput);
                
                $response->setMessage([
                    'result' => $result,
                    'final_state' => 'S' . $result,
                    'decimal_value' => $decimalValue
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

// Simple in-memory repository with optional Redis backing
final class FSMRepository {
    private array $storage = [];
    private ?Redis $redis = null;
    
    public function __construct(?Redis $redis = null) {
        $this->redis = $redis;
    }
    
    public function save(FSMInstance $fsm): void {
        $data = [
            'id' => $fsm->getId(),
            'definition' => serialize($fsm->getDefinition()),
            'current_state' => $fsm->getCurrentState(),
            'history' => $fsm->getHistory()
        ];
        
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
            return $this->hydrate($this->storage[$id]);
        }
        
        // Try Redis
        if ($this->redis) {
            $data = $this->redis->get('fsm:' . $id);
            if ($data) {
                $data = json_decode($data, true);
                $this->storage[$id] = $data; // Cache in memory
                return $this->hydrate($data);
            }
        }
        
        return null;
    }
    
    private function hydrate(array $data): FSMInstance {
        $fsm = new FSMInstance(
            id: $data['id'],
            definition: unserialize($data['definition'])
        );
        
        // Restore state and history
        $reflection = new \ReflectionClass($fsm);
        
        $stateProperty = $reflection->getProperty('currentState');
        $stateProperty->setAccessible(true);
        $stateProperty->setValue($fsm, $data['current_state']);
        
        $historyProperty = $reflection->getProperty('history');
        $historyProperty->setAccessible(true);
        $historyProperty->setValue($fsm, $data['history']);
        
        return $fsm;
    }
}
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
        
        // Update FSM state
        $reflection = new \ReflectionClass($fsm);
        $stateProperty = $reflection->getProperty('currentState');
        $stateProperty->setAccessible(true);
        $stateProperty->setValue($fsm, $currentState);
        
        $historyProperty = $reflection->getProperty('history');
        $historyProperty->setAccessible(true);
        $historyProperty->setValue($fsm, array_merge($fsm->getHistory(), $history));
        
        return new ExecutionResult(
            finalState: $currentState,
            transitions: $history,
            executionTime: microtime(true) - $startTime
        );
    }
    
    private function getCompiledTable(FSMDefinition $definition): array {
        $key = spl_object_hash($definition);
        
        if (!isset(self::$compiledTables[$key])) {
            self::$compiledTables[$key] = $this->compileTable($definition);
        }
        
        return self::$compiledTables[$key];
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
        
        // Verify correctness
        $expected = gmp_mod(gmp_init($largeInput, 2), 3);
        $this->assertEquals($expected, $result);
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

## 8. Key Simplifications from v1

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

## Conclusion

This simplified architecture provides a clean, maintainable FSM library that:
- **Handles the modulo-3 example efficiently** with optimized transition tables
- **Uses modern gRPC** for efficient communication
- **Leverages OpenSwoole** for high-performance async processing
- **Maintains code quality** without overengineering
- **Is production-ready** but pragmatic

The implementation focuses on what matters: a fast, correct FSM engine with a clean API, avoiding unnecessary complexity while maintaining professional code standards.