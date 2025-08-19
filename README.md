# FSM Library - Production-Ready Finite State Machine

A high-performance, production-ready Finite State Machine (FSM) library for PHP, implementing formal automata theory with clean Domain-Driven Design (DDD) principles.

## Features

- **Mathematical Rigor**: Strict adherence to formal 5-tuple FSM definition (Q, Σ, q0, F, δ)
- **Clean Architecture**: Hexagonal/Ports & Adapters with clear separation of concerns
- **Developer-Friendly**: Fluent builder API for easy FSM construction
- **High Performance**: Compiled automata for O(1) state transitions
- **Transport Agnostic**: Support for both gRPC and REST APIs
- **Production Ready**: Comprehensive testing, monitoring, and deployment support

## Installation

```bash
composer require fsm/library
```

## Quick Start

### Creating a Simple FSM

```php
use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;

// Create an FSM that detects even/odd number of 1s
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

// Execute the FSM
$result = $automaton->execute(new InputString('110101'));
echo $result->isAccepted ? 'Even number of 1s' : 'Odd number of 1s';
```

### Using the Modulo-3 Calculator

```php
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;

// Calculate n mod 3 where n is given as binary string
$binary = '1011';  // 11 in decimal
$result = ModuloThreeAutomaton::calculate($binary);
echo "{$binary} mod 3 = {$result}";  // Output: 1011 mod 3 = 2
```

## Architecture

The library follows Domain-Driven Design with three main layers:

### Core Domain Layer
- `FiniteAutomaton`: The mathematical 5-tuple model
- Value Objects: `State`, `StateSet`, `Symbol`, `Alphabet`, `TransitionFunction`
- `AutomatonBuilder`: Fluent API for FSM construction
- `ComputationResult`: Execution results with transition history

### Application Layer
- Commands and Queries for FSM operations
- Use case handlers for business logic orchestration
- Domain services for complex operations

### Infrastructure Layer
- Repository implementations (Redis, In-memory)
- Transport adapters (gRPC, REST)
- Serialization and persistence

## Performance

The library includes performance optimizations:

- **CompiledAutomaton**: Pre-computed transition tables for O(1) lookups
- **Batch Processing**: Process multiple inputs in parallel
- **Optimized Data Structures**: Integer indices for fast array access

```php
use FSM\Core\Performance\CompiledAutomaton;

$automaton = ModuloThreeAutomaton::getInstance();
$compiled = CompiledAutomaton::compile($automaton);

// Use compiled automaton for maximum performance
$stateIndex = $compiled->initialStateIndex;
foreach (str_split($binaryInput) as $char) {
    $symbolIndex = $compiled->symbolIndices[$char];
    $stateIndex = $compiled->transitionTable[$stateIndex][$symbolIndex];
}
$finalState = $compiled->states[$stateIndex];
```

## Testing

Run the test suite:

```bash
# All tests
composer test

# With coverage
composer test-coverage

# Specific test suite
./vendor/bin/phpunit --testsuite Unit
./vendor/bin/phpunit --testsuite Performance

# Code quality checks
composer quality
```

## Examples

See the `examples/` directory for comprehensive examples:

- `basic-usage.php`: Common FSM patterns and usage
- Pattern matching automata
- Binary arithmetic FSMs
- Performance comparisons

## Docker Deployment

```bash
# Build and run with Docker Compose
docker-compose up -d

# gRPC server will be available on port 9080
# REST API will be available on port 8080
```

## API Usage

### REST API

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
    }
  }'

# Execute FSM
curl -X POST http://localhost:8080/api/fsm/{id}/execute \
  -H "Content-Type: application/json" \
  -d '{"input": "110101"}'
```

### gRPC

See `proto/fsm.proto` for the complete service definition.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests (`composer test`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

MIT License - see LICENSE file for details

## References

- [Formal Automata Theory](https://en.wikipedia.org/wiki/Finite-state_machine)
- [Domain-Driven Design](https://dddcommunity.org/)
- [Hexagonal Architecture](https://alistair.cockburn.us/hexagonal-architecture/)