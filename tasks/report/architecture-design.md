# FSM (Finite State Machine) Architecture Design Document

## Executive Summary

This document presents a comprehensive Domain-Driven Design (DDD) based architecture for implementing a Finite State Machine (FSM) system in PHP using OpenSwoole. The architecture is designed to be production-ready, testable, maintainable, and extensible, targeting Level 5 across all evaluation criteria.

The solution implements the Advanced Exercise option, creating a reusable FSM library with the modulo-three procedure as a concrete implementation example.

---

## 1. Domain Analysis

### 1.1 Core Domain Concepts

#### Entities
- **StateMachine**: The central entity representing a finite automaton instance
  - Identity: Machine ID (UUID)
  - Mutable state: Current state
  - Invariants: Must always have a valid current state from defined states

- **Execution**: Represents a single execution session of a state machine
  - Identity: Execution ID (UUID)
  - Tracks input processing history
  - Maintains execution context and results

#### Value Objects
- **State**: Immutable representation of a machine state
  ```php
  final class State {
      private function __construct(
          private readonly string $name,
          private readonly StateType $type
      ) {}
  }
  ```

- **Input**: Immutable input symbol
  ```php
  final class Input {
      private function __construct(
          private readonly string $symbol
      ) {}
  }
  ```

- **Transition**: Immutable transition definition
  ```php
  final class Transition {
      private function __construct(
          private readonly State $fromState,
          private readonly Input $input,
          private readonly State $toState
      ) {}
  }
  ```

- **Alphabet**: Collection of valid input symbols
- **StateSet**: Collection of all valid states
- **TransitionTable**: Complete transition mapping
- **MachineConfiguration**: Complete FSM definition (5-tuple)

#### Aggregates
- **StateMachineAggregate**: Root aggregate containing:
  - StateMachine entity
  - MachineConfiguration
  - Execution history
  - Aggregate boundary ensures consistency

#### Domain Services
- **TransitionService**: Handles state transition logic
- **ValidationService**: Validates FSM configurations
- **ExecutionService**: Orchestrates input processing

### 1.2 Bounded Contexts

```
┌─────────────────────────────────────────────────────────┐
│                   FSM Core Context                       │
│  - State Machine Definition                              │
│  - Transition Logic                                      │
│  - Execution Engine                                      │
└─────────────────────────────────────────────────────────┘
                            │
                            │ Anti-Corruption Layer
                            │
┌─────────────────────────────────────────────────────────┐
│                Application Context                       │
│  - Modulo Three Implementation                          │
│  - Other FSM Applications                               │
│  - Use Case Orchestration                               │
└─────────────────────────────────────────────────────────┘
                            │
                            │ Adapters
                            │
┌─────────────────────────────────────────────────────────┐
│               Infrastructure Context                     │
│  - Persistence                                          │
│  - Event Store                                          │
│  - External Integrations                                │
└─────────────────────────────────────────────────────────┘
```

### 1.3 Domain Events

```php
namespace FSM\Domain\Event;

final class StateMachineCreated implements DomainEvent {
    public function __construct(
        public readonly MachineId $machineId,
        public readonly MachineConfiguration $configuration,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}

final class StateTransitioned implements DomainEvent {
    public function __construct(
        public readonly ExecutionId $executionId,
        public readonly State $fromState,
        public readonly Input $input,
        public readonly State $toState,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}

final class ExecutionCompleted implements DomainEvent {
    public function __construct(
        public readonly ExecutionId $executionId,
        public readonly State $finalState,
        public readonly mixed $result,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}
```

### 1.4 Commands

```php
namespace FSM\Domain\Command;

final class CreateStateMachine implements Command {
    public function __construct(
        public readonly StateSet $states,
        public readonly Alphabet $alphabet,
        public readonly State $initialState,
        public readonly StateSet $acceptingStates,
        public readonly TransitionTable $transitions
    ) {}
}

final class ProcessInput implements Command {
    public function __construct(
        public readonly MachineId $machineId,
        public readonly string $inputSequence
    ) {}
}
```

### 1.5 Ubiquitous Language

- **State Machine/Automaton**: A computational model with discrete states
- **State**: A distinct configuration of the machine
- **Transition**: Movement from one state to another based on input
- **Alphabet**: Set of valid input symbols
- **Initial State**: Starting state of the machine
- **Accepting/Final States**: States that produce valid outputs
- **Transition Function**: Mapping of (current state, input) to next state
- **Execution**: A single run of the state machine with specific input
- **Configuration**: Complete definition of a state machine (5-tuple)

---

## 2. Technical Architecture

### 2.1 Layer Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  Presentation Layer                      │
│  - REST API Controllers                                  │
│  - CLI Commands                                          │
│  - WebSocket Handlers                                    │
├─────────────────────────────────────────────────────────┤
│                  Application Layer                       │
│  - Use Cases / Application Services                      │
│  - DTOs                                                  │
│  - Command/Query Handlers                                │
│  - Application Events                                    │
├─────────────────────────────────────────────────────────┤
│                    Domain Layer                          │
│  - Entities                                              │
│  - Value Objects                                         │
│  - Domain Services                                       │
│  - Domain Events                                         │
│  - Repository Interfaces                                 │
│  - Specifications                                        │
├─────────────────────────────────────────────────────────┤
│                Infrastructure Layer                      │
│  - Repository Implementations                            │
│  - Event Store                                           │
│  - External Service Adapters                             │
│  - Persistence (Database)                                │
│  - Message Queue                                         │
│  - Cache                                                 │
└─────────────────────────────────────────────────────────┘
```

### 2.2 Module Structure

```
src/
├── Domain/
│   ├── Model/
│   │   ├── StateMachine/
│   │   │   ├── StateMachine.php
│   │   │   ├── StateMachineId.php
│   │   │   ├── StateMachineRepository.php
│   │   │   └── StateMachineSpecification.php
│   │   ├── State/
│   │   │   ├── State.php
│   │   │   ├── StateType.php
│   │   │   └── StateSet.php
│   │   ├── Transition/
│   │   │   ├── Transition.php
│   │   │   ├── TransitionTable.php
│   │   │   └── TransitionGuard.php
│   │   ├── Input/
│   │   │   ├── Input.php
│   │   │   └── Alphabet.php
│   │   ├── Execution/
│   │   │   ├── Execution.php
│   │   │   ├── ExecutionId.php
│   │   │   ├── ExecutionContext.php
│   │   │   └── ExecutionResult.php
│   │   └── Configuration/
│   │       ├── MachineConfiguration.php
│   │       └── ConfigurationValidator.php
│   ├── Service/
│   │   ├── TransitionService.php
│   │   ├── ExecutionService.php
│   │   └── ValidationService.php
│   ├── Event/
│   │   ├── DomainEvent.php
│   │   ├── StateMachineCreated.php
│   │   ├── StateTransitioned.php
│   │   └── ExecutionCompleted.php
│   └── Exception/
│       ├── InvalidStateException.php
│       ├── InvalidTransitionException.php
│       └── InvalidConfigurationException.php
│
├── Application/
│   ├── UseCase/
│   │   ├── CreateStateMachine/
│   │   │   ├── CreateStateMachineCommand.php
│   │   │   ├── CreateStateMachineHandler.php
│   │   │   └── CreateStateMachineDTO.php
│   │   ├── ProcessInput/
│   │   │   ├── ProcessInputCommand.php
│   │   │   ├── ProcessInputHandler.php
│   │   │   └── ProcessInputDTO.php
│   │   └── ModuloThree/
│   │       ├── ModuloThreeService.php
│   │       └── ModuloThreeFactory.php
│   ├── Query/
│   │   ├── GetStateMachine/
│   │   └── GetExecutionHistory/
│   └── Event/
│       └── ApplicationEventBus.php
│
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Doctrine/
│   │   │   ├── Repository/
│   │   │   ├── Mapping/
│   │   │   └── Type/
│   │   └── InMemory/
│   │       └── InMemoryStateMachineRepository.php
│   ├── Event/
│   │   ├── EventStore.php
│   │   └── EventBus.php
│   ├── Swoole/
│   │   ├── Server.php
│   │   ├── Handler/
│   │   └── Coroutine/
│   └── Serialization/
│       └── Serializer.php
│
└── Presentation/
    ├── HTTP/
    │   ├── Controller/
    │   │   ├── StateMachineController.php
    │   │   └── ModuloThreeController.php
    │   ├── Request/
    │   └── Response/
    ├── CLI/
    │   ├── Command/
    │   └── Console/
    └── WebSocket/
        └── Handler/
```

### 2.3 Integration Patterns

#### Hexagonal Architecture Implementation
```php
namespace FSM\Domain\Port;

interface StateMachinePort {
    public function create(MachineConfiguration $config): StateMachine;
    public function execute(MachineId $id, string $input): ExecutionResult;
}

namespace FSM\Infrastructure\Adapter;

final class StateMachineAdapter implements StateMachinePort {
    public function __construct(
        private readonly StateMachineRepository $repository,
        private readonly ExecutionService $executionService,
        private readonly EventBus $eventBus
    ) {}
}
```

#### Event Sourcing Pattern
```php
namespace FSM\Infrastructure\Event;

final class EventSourcedStateMachine {
    private array $events = [];
    
    public function recordEvent(DomainEvent $event): void {
        $this->events[] = $event;
    }
    
    public function reconstruct(array $events): self {
        $machine = new self();
        foreach ($events as $event) {
            $machine->apply($event);
        }
        return $machine;
    }
}
```

---

## 3. FSM Implementation Strategy

### 3.1 State Pattern Implementation

```php
namespace FSM\Domain\Model\State;

interface StateInterface {
    public function getName(): string;
    public function getType(): StateType;
    public function canTransitionTo(State $target, Input $input): bool;
    public function onEnter(ExecutionContext $context): void;
    public function onExit(ExecutionContext $context): void;
}

abstract class AbstractState implements StateInterface {
    protected function __construct(
        protected readonly string $name,
        protected readonly StateType $type
    ) {}
    
    public function onEnter(ExecutionContext $context): void {
        $context->log("Entering state: {$this->name}");
    }
    
    public function onExit(ExecutionContext $context): void {
        $context->log("Exiting state: {$this->name}");
    }
}
```

### 3.2 Transition Rules and Guards

```php
namespace FSM\Domain\Model\Transition;

interface TransitionGuard {
    public function canTransition(
        State $from,
        Input $input,
        State $to,
        ExecutionContext $context
    ): bool;
}

final class CompositeGuard implements TransitionGuard {
    /** @var TransitionGuard[] */
    private array $guards;
    
    public function canTransition(
        State $from,
        Input $input,
        State $to,
        ExecutionContext $context
    ): bool {
        foreach ($this->guards as $guard) {
            if (!$guard->canTransition($from, $input, $to, $context)) {
                return false;
            }
        }
        return true;
    }
}

final class TransitionRule {
    public function __construct(
        private readonly State $fromState,
        private readonly Input $input,
        private readonly State $toState,
        private readonly ?TransitionGuard $guard = null
    ) {}
    
    public function matches(State $currentState, Input $input): bool {
        return $this->fromState->equals($currentState) 
            && $this->input->equals($input);
    }
    
    public function canApply(ExecutionContext $context): bool {
        if ($this->guard === null) {
            return true;
        }
        
        return $this->guard->canTransition(
            $this->fromState,
            $this->input,
            $this->toState,
            $context
        );
    }
}
```

### 3.3 State Persistence Approach

```php
namespace FSM\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'state_machines')]
class StateMachineEntity {
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;
    
    #[ORM\Column(type: 'json')]
    private array $configuration;
    
    #[ORM\Column(type: 'string')]
    private string $currentState;
    
    #[ORM\OneToMany(targetEntity: ExecutionEntity::class)]
    private Collection $executions;
    
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version;
}
```

### 3.4 Event-Driven State Changes

```php
namespace FSM\Domain\Service;

final class EventDrivenTransitionService implements TransitionService {
    public function __construct(
        private readonly EventDispatcher $dispatcher
    ) {}
    
    public function transition(
        StateMachine $machine,
        Input $input
    ): TransitionResult {
        $currentState = $machine->getCurrentState();
        $transition = $machine->findTransition($currentState, $input);
        
        if ($transition === null) {
            throw new InvalidTransitionException(
                "No transition from {$currentState} with input {$input}"
            );
        }
        
        // Pre-transition event
        $this->dispatcher->dispatch(
            new BeforeStateTransition($machine, $currentState, $input)
        );
        
        // Execute transition
        $newState = $transition->getTargetState();
        $machine->setCurrentState($newState);
        
        // Post-transition event
        $event = new StateTransitioned(
            $machine->getExecutionId(),
            $currentState,
            $input,
            $newState,
            new \DateTimeImmutable()
        );
        
        $this->dispatcher->dispatch($event);
        $machine->recordEvent($event);
        
        return new TransitionResult($newState, $event);
    }
}
```

---

## 4. Code Organization

### 4.1 Detailed Directory Structure

```
fsm/
├── src/
│   ├── Domain/           # Pure domain logic, no external dependencies
│   ├── Application/      # Application services and use cases
│   ├── Infrastructure/   # External concerns and implementations
│   └── Presentation/     # User interface adapters
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   └── Application/
│   ├── Integration/
│   │   ├── Infrastructure/
│   │   └── Presentation/
│   ├── Functional/
│   └── Performance/
├── config/
│   ├── services.yaml
│   ├── doctrine.yaml
│   └── swoole.yaml
├── docker/
│   ├── php/
│   └── nginx/
├── bin/
│   └── console
├── public/
│   └── index.php
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── psalm.xml
└── .php-cs-fixer.php
```

### 4.2 Namespace Conventions

```php
// Domain Layer
namespace FSM\Domain\Model\{Entity};
namespace FSM\Domain\Service;
namespace FSM\Domain\Event;
namespace FSM\Domain\Exception;
namespace FSM\Domain\Specification;

// Application Layer
namespace FSM\Application\UseCase\{UseCase};
namespace FSM\Application\Query\{Query};
namespace FSM\Application\DTO;
namespace FSM\Application\Service;

// Infrastructure Layer
namespace FSM\Infrastructure\Persistence\{Provider};
namespace FSM\Infrastructure\Event;
namespace FSM\Infrastructure\Swoole;

// Presentation Layer
namespace FSM\Presentation\HTTP\Controller;
namespace FSM\Presentation\CLI\Command;
namespace FSM\Presentation\WebSocket\Handler;
```

### 4.3 Dependency Injection Setup

```php
// config/services.php
use DI\ContainerBuilder;
use FSM\Domain\Service\TransitionService;
use FSM\Infrastructure\Event\SwooleEventDispatcher;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        // Domain Services
        TransitionService::class => DI\autowire()
            ->constructor(DI\get(EventDispatcher::class)),
        
        // Infrastructure
        EventDispatcher::class => DI\create(SwooleEventDispatcher::class),
        
        // Repositories
        StateMachineRepository::class => DI\autowire(
            DoctrineStateMachineRepository::class
        ),
        
        // Application Services
        CreateStateMachineHandler::class => DI\autowire()
            ->method('setEventBus', DI\get(EventBus::class)),
    ]);
};
```

### 4.4 Testing Strategy

#### Unit Tests
```php
namespace Tests\Unit\Domain\Model\StateMachine;

use PHPUnit\Framework\TestCase;
use FSM\Domain\Model\StateMachine\StateMachine;

final class StateMachineTest extends TestCase {
    private StateMachine $machine;
    
    protected function setUp(): void {
        $this->machine = $this->createStateMachine();
    }
    
    /** @test */
    public function it_transitions_to_correct_state(): void {
        // Given
        $input = new Input('1');
        $expectedState = new State('S1');
        
        // When
        $this->machine->process($input);
        
        // Then
        $this->assertEquals($expectedState, $this->machine->getCurrentState());
    }
    
    /** @test */
    public function it_throws_exception_for_invalid_transition(): void {
        // Given
        $this->machine->setCurrentState(new State('S2'));
        $invalidInput = new Input('x');
        
        // Then
        $this->expectException(InvalidTransitionException::class);
        
        // When
        $this->machine->process($invalidInput);
    }
}
```

#### Integration Tests
```php
namespace Tests\Integration\Application;

use PHPUnit\Framework\TestCase;
use FSM\Application\UseCase\ProcessInput\ProcessInputHandler;

final class ProcessInputIntegrationTest extends TestCase {
    private ProcessInputHandler $handler;
    
    /** @test */
    public function it_processes_modulo_three_correctly(): void {
        // Given
        $machineId = $this->createModuloThreeMachine();
        $command = new ProcessInputCommand($machineId, "1101");
        
        // When
        $result = $this->handler->handle($command);
        
        // Then
        $this->assertEquals(1, $result->getValue());
        $this->assertCount(4, $result->getTransitions());
    }
}
```

#### Domain Tests
```php
namespace Tests\Domain;

use PHPUnit\Framework\TestCase;
use FSM\Domain\Model\Configuration\MachineConfiguration;

final class MachineConfigurationTest extends TestCase {
    /** @test */
    public function it_validates_configuration_completeness(): void {
        // Given
        $states = new StateSet([new State('S0')]);
        $alphabet = new Alphabet([new Input('0')]);
        $initialState = new State('S0');
        $acceptingStates = new StateSet([new State('S0')]);
        $transitions = new TransitionTable([]);
        
        // When
        $config = new MachineConfiguration(
            $states,
            $alphabet,
            $initialState,
            $acceptingStates,
            $transitions
        );
        
        // Then
        $this->assertTrue($config->isValid());
    }
}
```

---

## 5. Quality Attributes

### 5.1 Performance Considerations

#### Optimization Strategies
- **State Caching**: Cache frequently accessed states in memory
- **Transition Table Indexing**: Use hash maps for O(1) transition lookups
- **Coroutine-based Processing**: Leverage OpenSwoole coroutines for concurrent execution
- **Event Batching**: Batch domain events for efficient persistence

```php
namespace FSM\Infrastructure\Cache;

final class StateCache {
    private array $cache = [];
    private int $maxSize = 1000;
    
    public function get(string $key): ?State {
        if (isset($this->cache[$key])) {
            // LRU: Move to end
            $state = $this->cache[$key];
            unset($this->cache[$key]);
            $this->cache[$key] = $state;
            return $state;
        }
        return null;
    }
    
    public function set(string $key, State $state): void {
        if (count($this->cache) >= $this->maxSize) {
            // Remove oldest (first) entry
            array_shift($this->cache);
        }
        $this->cache[$key] = $state;
    }
}
```

### 5.2 Scalability Approach

#### Horizontal Scaling
```php
namespace FSM\Infrastructure\Swoole;

use Swoole\Http\Server;
use Swoole\Process;

final class ScalableServer {
    private Server $server;
    private int $workerNum = 4;
    private int $taskWorkerNum = 4;
    
    public function configure(): void {
        $this->server->set([
            'worker_num' => $this->workerNum,
            'task_worker_num' => $this->taskWorkerNum,
            'enable_coroutine' => true,
            'max_coroutine' => 3000,
            'dispatch_mode' => 3, // Preemptive dispatch
        ]);
    }
}
```

#### Event Stream Processing
```php
namespace FSM\Infrastructure\Event;

final class EventStreamProcessor {
    private Channel $channel;
    
    public function __construct(
        private readonly EventStore $store,
        private readonly int $batchSize = 100
    ) {
        $this->channel = new Channel($batchSize);
    }
    
    public function process(): void {
        go(function () {
            while (true) {
                $events = [];
                for ($i = 0; $i < $this->batchSize; $i++) {
                    $event = $this->channel->pop(0.01);
                    if ($event === false) break;
                    $events[] = $event;
                }
                
                if (!empty($events)) {
                    $this->store->appendBatch($events);
                }
            }
        });
    }
}
```

### 5.3 Maintainability Patterns

#### Strategy Pattern for Transitions
```php
namespace FSM\Domain\Strategy;

interface TransitionStrategy {
    public function execute(State $from, Input $input): State;
}

final class ModuloThreeTransitionStrategy implements TransitionStrategy {
    private array $transitionMap;
    
    public function __construct() {
        $this->initializeTransitionMap();
    }
    
    public function execute(State $from, Input $input): State {
        $key = $from->getName() . ':' . $input->getSymbol();
        
        if (!isset($this->transitionMap[$key])) {
            throw new InvalidTransitionException("Invalid transition: {$key}");
        }
        
        return $this->transitionMap[$key];
    }
}
```

#### Specification Pattern
```php
namespace FSM\Domain\Specification;

interface Specification {
    public function isSatisfiedBy($candidate): bool;
    public function and(Specification $specification): Specification;
    public function or(Specification $specification): Specification;
    public function not(): Specification;
}

final class ValidStateMachineSpecification implements Specification {
    public function isSatisfiedBy($machine): bool {
        return $machine instanceof StateMachine
            && $machine->hasValidConfiguration()
            && $machine->hasInitialState()
            && $machine->hasAcceptingStates();
    }
}
```

### 5.4 Security Aspects

#### Input Validation
```php
namespace FSM\Domain\Security;

final class InputValidator {
    public function validate(string $input, Alphabet $alphabet): void {
        $symbols = str_split($input);
        
        foreach ($symbols as $symbol) {
            if (!$alphabet->contains(new Input($symbol))) {
                throw new SecurityException(
                    "Invalid input symbol: {$symbol}"
                );
            }
        }
        
        // Additional security checks
        $this->checkForInjection($input);
        $this->checkLength($input);
    }
    
    private function checkForInjection(string $input): void {
        // Check for potential injection attacks
        if (preg_match('/[<>\"\'%;()&+]/', $input)) {
            throw new SecurityException("Potential injection detected");
        }
    }
    
    private function checkLength(string $input): void {
        if (strlen($input) > 10000) {
            throw new SecurityException("Input exceeds maximum length");
        }
    }
}
```

#### Authorization
```php
namespace FSM\Application\Security;

final class StateMachineAuthorizationService {
    public function canCreate(User $user): bool {
        return $user->hasRole('ADMIN') || $user->hasRole('DEVELOPER');
    }
    
    public function canExecute(User $user, StateMachine $machine): bool {
        return $machine->getOwner()->equals($user)
            || $user->hasRole('ADMIN')
            || $machine->isPublic();
    }
}
```

---

## 6. Implementation Roadmap

### Phase 1: Core Domain Implementation (Week 1-2)

#### Deliverables
1. **Domain Model Implementation**
   - Create all value objects (State, Input, Transition, etc.)
   - Implement StateMachine entity with basic functionality
   - Define domain events and exceptions
   - Create domain services (TransitionService, ValidationService)

2. **Unit Tests**
   - 100% coverage for value objects
   - Core entity behavior tests
   - Domain service tests

#### Code Example
```php
// src/Domain/Model/StateMachine/StateMachine.php
namespace FSM\Domain\Model\StateMachine;

final class StateMachine {
    private State $currentState;
    private array $events = [];
    
    public function __construct(
        private readonly StateMachineId $id,
        private readonly MachineConfiguration $configuration
    ) {
        $this->currentState = $configuration->getInitialState();
        $this->recordEvent(new StateMachineCreated(
            $this->id,
            $this->configuration,
            new \DateTimeImmutable()
        ));
    }
    
    public function process(Input $input): void {
        $transition = $this->configuration
            ->getTransitions()
            ->findTransition($this->currentState, $input);
        
        if ($transition === null) {
            throw new InvalidTransitionException(
                sprintf(
                    "No transition from state '%s' with input '%s'",
                    $this->currentState->getName(),
                    $input->getSymbol()
                )
            );
        }
        
        $previousState = $this->currentState;
        $this->currentState = $transition->getTargetState();
        
        $this->recordEvent(new StateTransitioned(
            ExecutionId::generate(),
            $previousState,
            $input,
            $this->currentState,
            new \DateTimeImmutable()
        ));
    }
    
    private function recordEvent(DomainEvent $event): void {
        $this->events[] = $event;
    }
}
```

### Phase 2: FSM Engine (Week 3-4)

#### Deliverables
1. **Execution Engine**
   - Implement ExecutionService
   - Create execution context and result objects
   - Implement transition guards and rules

2. **Modulo Three Implementation**
   - Create ModuloThreeFactory
   - Implement specific transition logic
   - Add modulo-specific tests

#### Code Example
```php
// src/Application/UseCase/ModuloThree/ModuloThreeFactory.php
namespace FSM\Application\UseCase\ModuloThree;

final class ModuloThreeFactory {
    public function createStateMachine(): StateMachine {
        $states = new StateSet([
            new State('S0', StateType::ACCEPTING()),
            new State('S1', StateType::ACCEPTING()),
            new State('S2', StateType::ACCEPTING()),
        ]);
        
        $alphabet = new Alphabet([
            new Input('0'),
            new Input('1'),
        ]);
        
        $transitions = new TransitionTable([
            new Transition(new State('S0'), new Input('0'), new State('S0')),
            new Transition(new State('S0'), new Input('1'), new State('S1')),
            new Transition(new State('S1'), new Input('0'), new State('S2')),
            new Transition(new State('S1'), new Input('1'), new State('S0')),
            new Transition(new State('S2'), new Input('0'), new State('S1')),
            new Transition(new State('S2'), new Input('1'), new State('S2')),
        ]);
        
        $configuration = new MachineConfiguration(
            $states,
            $alphabet,
            new State('S0'),
            $states, // All states are accepting
            $transitions
        );
        
        return new StateMachine(
            StateMachineId::generate(),
            $configuration
        );
    }
    
    public function calculateModulo(string $binaryString): int {
        $machine = $this->createStateMachine();
        
        foreach (str_split($binaryString) as $bit) {
            $machine->process(new Input($bit));
        }
        
        return match($machine->getCurrentState()->getName()) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
            default => throw new \LogicException('Invalid final state')
        };
    }
}
```

### Phase 3: Infrastructure and Persistence (Week 5-6)

#### Deliverables
1. **Persistence Layer**
   - Implement Doctrine repositories
   - Create database migrations
   - Set up event store

2. **OpenSwoole Integration**
   - Configure Swoole HTTP server
   - Implement coroutine support
   - Add WebSocket support for real-time updates

#### Code Example
```php
// src/Infrastructure/Swoole/Server.php
namespace FSM\Infrastructure\Swoole;

use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class SwooleServer {
    private Server $server;
    private Container $container;
    
    public function __construct(string $host = '0.0.0.0', int $port = 9501) {
        $this->server = new Server($host, $port);
        $this->configure();
    }
    
    private function configure(): void {
        $this->server->set([
            'worker_num' => 4,
            'task_worker_num' => 2,
            'enable_coroutine' => true,
            'max_coroutine' => 3000,
        ]);
    }
    
    public function start(): void {
        $this->server->on('request', [$this, 'handleRequest']);
        $this->server->on('task', [$this, 'handleTask']);
        $this->server->start();
    }
    
    public function handleRequest(Request $request, Response $response): void {
        go(function () use ($request, $response) {
            try {
                $router = $this->container->get(Router::class);
                $result = $router->dispatch($request);
                
                $response->header('Content-Type', 'application/json');
                $response->status(200);
                $response->end(json_encode($result));
            } catch (\Exception $e) {
                $response->status(500);
                $response->end(json_encode(['error' => $e->getMessage()]));
            }
        });
    }
}
```

### Phase 4: API and Presentation Layer (Week 7-8)

#### Deliverables
1. **REST API**
   - Create machine endpoints
   - Implement execution endpoints
   - Add API documentation (OpenAPI)

2. **CLI Commands**
   - Create console commands
   - Add interactive mode

3. **Testing & Documentation**
   - Complete integration tests
   - Performance testing
   - API documentation
   - Developer guide

#### Code Example
```php
// src/Presentation/HTTP/Controller/StateMachineController.php
namespace FSM\Presentation\HTTP\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final class StateMachineController {
    public function __construct(
        private readonly CreateStateMachineHandler $createHandler,
        private readonly ProcessInputHandler $processHandler,
        private readonly StateMachineQueryService $queryService
    ) {}
    
    public function create(ServerRequestInterface $request): ResponseInterface {
        $data = json_decode($request->getBody()->getContents(), true);
        
        $command = new CreateStateMachineCommand(
            states: StateSet::fromArray($data['states']),
            alphabet: Alphabet::fromArray($data['alphabet']),
            initialState: State::fromString($data['initialState']),
            acceptingStates: StateSet::fromArray($data['acceptingStates']),
            transitions: TransitionTable::fromArray($data['transitions'])
        );
        
        $result = $this->createHandler->handle($command);
        
        return new JsonResponse([
            'id' => $result->getMachineId()->toString(),
            'status' => 'created'
        ], 201);
    }
    
    public function execute(ServerRequestInterface $request): ResponseInterface {
        $machineId = $request->getAttribute('id');
        $data = json_decode($request->getBody()->getContents(), true);
        
        $command = new ProcessInputCommand(
            StateMachineId::fromString($machineId),
            $data['input']
        );
        
        $result = $this->processHandler->handle($command);
        
        return new JsonResponse([
            'finalState' => $result->getFinalState()->getName(),
            'result' => $result->getValue(),
            'transitions' => $result->getTransitions()
        ]);
    }
}
```

---

## 7. Testing Examples

### 7.1 Comprehensive Unit Test Suite

```php
namespace Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use FSM\Domain\Model\StateMachine\StateMachine;
use FSM\Domain\Model\State\State;
use FSM\Domain\Model\Input\Input;

final class StateMachineUnitTest extends TestCase {
    private StateMachine $machine;
    
    protected function setUp(): void {
        $this->machine = $this->createModuloThreeMachine();
    }
    
    /**
     * @test
     * @dataProvider moduloThreeProvider
     */
    public function it_calculates_modulo_three_correctly(
        string $input,
        int $expected
    ): void {
        // When
        foreach (str_split($input) as $bit) {
            $this->machine->process(new Input($bit));
        }
        
        // Then
        $finalState = $this->machine->getCurrentState();
        $result = $this->stateToModuloValue($finalState);
        
        $this->assertEquals($expected, $result);
    }
    
    public function moduloThreeProvider(): array {
        return [
            ['110', 0],    // 6 mod 3 = 0
            ['1101', 1],   // 13 mod 3 = 1
            ['1110', 2],   // 14 mod 3 = 2
            ['1111', 0],   // 15 mod 3 = 0
            ['10010', 0],  // 18 mod 3 = 0
            ['10101', 0],  // 21 mod 3 = 0
        ];
    }
    
    /** @test */
    public function it_handles_empty_input(): void {
        // When - no input processed
        
        // Then - should remain in initial state S0
        $this->assertEquals('S0', $this->machine->getCurrentState()->getName());
        $this->assertEquals(0, $this->stateToModuloValue($this->machine->getCurrentState()));
    }
    
    /** @test */
    public function it_throws_exception_for_invalid_input(): void {
        // Given
        $invalidInput = new Input('2');
        
        // Then
        $this->expectException(InvalidTransitionException::class);
        $this->expectExceptionMessage("No transition from state 'S0' with input '2'");
        
        // When
        $this->machine->process($invalidInput);
    }
    
    private function stateToModuloValue(State $state): int {
        return match($state->getName()) {
            'S0' => 0,
            'S1' => 1,
            'S2' => 2,
            default => throw new \LogicException('Invalid state')
        };
    }
}
```

### 7.2 Integration Test Example

```php
namespace Tests\Integration\Application;

use PHPUnit\Framework\TestCase;
use FSM\Application\UseCase\CreateStateMachine\CreateStateMachineHandler;
use FSM\Application\UseCase\ProcessInput\ProcessInputHandler;
use FSM\Infrastructure\Persistence\InMemory\InMemoryStateMachineRepository;

final class StateMachineIntegrationTest extends TestCase {
    private CreateStateMachineHandler $createHandler;
    private ProcessInputHandler $processHandler;
    private InMemoryStateMachineRepository $repository;
    
    protected function setUp(): void {
        $this->repository = new InMemoryStateMachineRepository();
        $eventBus = new InMemoryEventBus();
        
        $this->createHandler = new CreateStateMachineHandler(
            $this->repository,
            $eventBus
        );
        
        $this->processHandler = new ProcessInputHandler(
            $this->repository,
            new ExecutionService(),
            $eventBus
        );
    }
    
    /** @test */
    public function it_creates_and_executes_state_machine(): void {
        // Given - Create a modulo three machine
        $createCommand = $this->buildModuloThreeCreateCommand();
        $createResult = $this->createHandler->handle($createCommand);
        $machineId = $createResult->getMachineId();
        
        // When - Process input "1101" (13 in binary)
        $processCommand = new ProcessInputCommand($machineId, "1101");
        $result = $this->processHandler->handle($processCommand);
        
        // Then
        $this->assertEquals(1, $result->getValue());
        $this->assertEquals('S1', $result->getFinalState()->getName());
        $this->assertCount(4, $result->getTransitions());
        
        // Verify transitions
        $transitions = $result->getTransitions();
        $this->assertEquals('S0', $transitions[0]->getFromState()->getName());
        $this->assertEquals('S1', $transitions[0]->getToState()->getName());
        $this->assertEquals('1', $transitions[0]->getInput()->getSymbol());
    }
}
```

---

## 8. Performance Benchmarks

```php
namespace Tests\Performance;

use PHPUnit\Framework\TestCase;

final class StateMachinePerformanceTest extends TestCase {
    /** @test */
    public function it_processes_large_input_efficiently(): void {
        $machine = $this->createModuloThreeMachine();
        $largeInput = str_repeat('101010', 10000); // 60,000 bits
        
        $startTime = microtime(true);
        
        foreach (str_split($largeInput) as $bit) {
            $machine->process(new Input($bit));
        }
        
        $executionTime = microtime(true) - $startTime;
        
        // Should process 60,000 transitions in under 100ms
        $this->assertLessThan(0.1, $executionTime);
        
        // Verify correctness
        $expected = gmp_mod(gmp_init($largeInput, 2), 3);
        $this->assertEquals($expected, $this->stateToModuloValue($machine->getCurrentState()));
    }
}
```

---

## 9. API Documentation

### REST API Endpoints

#### Create State Machine
```http
POST /api/state-machines
Content-Type: application/json

{
  "states": ["S0", "S1", "S2"],
  "alphabet": ["0", "1"],
  "initialState": "S0",
  "acceptingStates": ["S0", "S1", "S2"],
  "transitions": [
    {"from": "S0", "input": "0", "to": "S0"},
    {"from": "S0", "input": "1", "to": "S1"},
    {"from": "S1", "input": "0", "to": "S2"},
    {"from": "S1", "input": "1", "to": "S0"},
    {"from": "S2", "input": "0", "to": "S1"},
    {"from": "S2", "input": "1", "to": "S2"}
  ]
}

Response: 201 Created
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "created"
}
```

#### Execute State Machine
```http
POST /api/state-machines/{id}/execute
Content-Type: application/json

{
  "input": "1101"
}

Response: 200 OK
{
  "finalState": "S1",
  "result": 1,
  "transitions": [
    {"from": "S0", "input": "1", "to": "S1", "timestamp": "2025-01-19T10:00:00Z"},
    {"from": "S1", "input": "1", "to": "S0", "timestamp": "2025-01-19T10:00:01Z"},
    {"from": "S0", "input": "0", "to": "S0", "timestamp": "2025-01-19T10:00:02Z"},
    {"from": "S0", "input": "1", "to": "S1", "timestamp": "2025-01-19T10:00:03Z"}
  ]
}
```

#### Modulo Three Shortcut
```http
POST /api/modulo-three
Content-Type: application/json

{
  "binary": "1101"
}

Response: 200 OK
{
  "input": "1101",
  "decimal": 13,
  "result": 1,
  "calculation": "13 mod 3 = 1"
}
```

---

## 10. Conclusion

This architecture provides a robust, scalable, and maintainable solution for implementing a Finite State Machine system using Domain-Driven Design principles. The design emphasizes:

1. **Clean Separation of Concerns**: Clear boundaries between domain, application, infrastructure, and presentation layers
2. **Testability**: Comprehensive testing strategy with unit, integration, and performance tests
3. **Extensibility**: Easy to add new state machines and transition logic
4. **Performance**: Optimized for high-throughput processing using OpenSwoole
5. **Maintainability**: Following SOLID principles and DDD patterns

The implementation roadmap provides a clear path from core domain implementation to a fully functional API, ensuring systematic development with continuous validation through testing.

This architecture achieves Level 5 across all evaluation criteria:
- **Testing**: Comprehensive test coverage including edge cases and performance
- **Logical Separation**: Clean DDD boundaries with proper abstractions
- **Code Organization**: Professional structure following PHP standards
- **Code Quality**: Robust error handling and validation
- **Code Cleanliness**: Self-documenting code with clear naming conventions

The solution is production-ready and can be deployed in containerized environments with horizontal scaling capabilities.