#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use FSM\Core\Performance\CompiledAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeService;
use FSM\Application\Service\FSMExecutor;
use FSM\Core\Model\FSMInstance;
use FSM\Core\Model\FSMMetadata;

echo "\n";
echo "========================================\n";
echo "   FSM Library Performance Benchmarks\n";
echo "========================================\n\n";

/**
 * Benchmark runner
 */
class Benchmark
{
    private array $results = [];
    
    public function run(string $name, callable $setup, callable $test, int $iterations = 1000): void
    {
        echo "Running: {$name}\n";
        
        // Warmup
        $data = $setup();
        for ($i = 0; $i < 10; $i++) {
            $test($data);
        }
        
        // Actual benchmark
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $test($data);
            $times[] = (microtime(true) - $start) * 1000; // Convert to ms
        }
        
        // Calculate statistics
        $avg = array_sum($times) / count($times);
        sort($times);
        $min = $times[0];
        $max = $times[count($times) - 1];
        $median = $times[(int)(count($times) / 2)];
        $p95 = $times[(int)(count($times) * 0.95)];
        $p99 = $times[(int)(count($times) * 0.99)];
        
        $this->results[$name] = [
            'iterations' => $iterations,
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'median' => $median,
            'p95' => $p95,
            'p99' => $p99,
            'ops_per_sec' => 1000 / $avg
        ];
        
        printf("  Avg: %.4f ms | Min: %.4f ms | Max: %.4f ms | Median: %.4f ms\n", 
            $avg, $min, $max, $median);
        printf("  P95: %.4f ms | P99: %.4f ms | Ops/sec: %.0f\n\n", 
            $p95, $p99, 1000 / $avg);
    }
    
    public function compare(string $name1, string $name2): void
    {
        if (!isset($this->results[$name1]) || !isset($this->results[$name2])) {
            return;
        }
        
        $speedup = $this->results[$name1]['avg'] / $this->results[$name2]['avg'];
        $percentage = (($speedup - 1) * 100);
        
        if ($speedup > 1) {
            printf("'{$name2}' is %.2fx faster (%.1f%% improvement)\n", 
                $speedup, $percentage);
        } else {
            printf("'{$name1}' is %.2fx faster (%.1f%% improvement)\n", 
                1/$speedup, -$percentage);
        }
    }
    
    public function summary(): void
    {
        echo "\n----------------------------------------\n";
        echo "Benchmark Summary\n";
        echo "----------------------------------------\n";
        
        foreach ($this->results as $name => $stats) {
            printf("%-30s: %.4f ms avg, %.0f ops/sec\n", 
                $name, $stats['avg'], $stats['ops_per_sec']);
        }
    }
}

$benchmark = new Benchmark();

// Benchmark 1: Basic FSM Execution
echo "1. BASIC FSM EXECUTION\n";
echo "----------------------\n";

$benchmark->run(
    'Simple FSM (10 transitions)',
    function() {
        $fsm = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'A')
            ->withTransition('A', '1', 'B')
            ->withTransition('B', '0', 'B')
            ->withTransition('B', '1', 'A')
            ->build();
        return ['fsm' => $fsm, 'input' => '0101010101'];
    },
    function($data) {
        $data['fsm']->execute(new InputString($data['input']));
    }
);

$benchmark->run(
    'Simple FSM (100 transitions)',
    function() {
        $fsm = AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'A')
            ->withTransition('A', '1', 'B')
            ->withTransition('B', '0', 'B')
            ->withTransition('B', '1', 'A')
            ->build();
        $input = str_repeat('01', 50);
        return ['fsm' => $fsm, 'input' => $input];
    },
    function($data) {
        $data['fsm']->execute(new InputString($data['input']));
    }
);

// Benchmark 2: Regular vs Compiled Automaton
echo "2. REGULAR VS COMPILED AUTOMATON\n";
echo "---------------------------------\n";

$benchmark->run(
    'Modulo-3 Regular (1000 bits)',
    function() {
        $fsm = ModuloThreeAutomaton::getInstance();
        $input = str_repeat('10110101', 125); // 1000 bits
        return ['fsm' => $fsm, 'input' => $input];
    },
    function($data) {
        $data['fsm']->execute(new InputString($data['input']));
    }
);

$benchmark->run(
    'Modulo-3 Compiled (1000 bits)',
    function() {
        $fsm = ModuloThreeAutomaton::getInstance();
        $compiled = CompiledAutomaton::compile($fsm);
        $input = str_repeat('10110101', 125); // 1000 bits
        return ['compiled' => $compiled, 'input' => $input];
    },
    function($data) {
        $stateIndex = $data['compiled']->initialStateIndex;
        foreach (str_split($data['input']) as $char) {
            $symbolIndex = $data['compiled']->symbolIndices[$char];
            $stateIndex = $data['compiled']->transitionTable[$stateIndex][$symbolIndex];
        }
    }
);

$benchmark->compare('Modulo-3 Regular (1000 bits)', 'Modulo-3 Compiled (1000 bits)');

// Benchmark 3: Multi-character symbols
echo "\n3. MULTI-CHARACTER SYMBOLS\n";
echo "---------------------------\n";

$benchmark->run(
    'Single-char symbols',
    function() {
        $fsm = AutomatonBuilder::create()
            ->withStates('S0', 'S1', 'S2')
            ->withAlphabet('0', '1')
            ->withInitialState('S0')
            ->withFinalStates('S2')
            ->withTransition('S0', '0', 'S1')
            ->withTransition('S0', '1', 'S0')
            ->withTransition('S1', '0', 'S2')
            ->withTransition('S1', '1', 'S0')
            ->withTransition('S2', '0', 'S2')
            ->withTransition('S2', '1', 'S2')
            ->build();
        return ['fsm' => $fsm, 'input' => str_repeat('01', 50)];
    },
    function($data) {
        $data['fsm']->execute(new InputString($data['input']));
    }
);

$benchmark->run(
    'Multi-char symbols',
    function() {
        $fsm = AutomatonBuilder::create()
            ->withStates('S0', 'S1')
            ->withAlphabet('00', '01', '10', '11')
            ->withInitialState('S0')
            ->withFinalStates('S1')
            ->withTransition('S0', '00', 'S0')
            ->withTransition('S0', '01', 'S1')
            ->withTransition('S0', '10', 'S1')
            ->withTransition('S0', '11', 'S0')
            ->withTransition('S1', '00', 'S1')
            ->withTransition('S1', '01', 'S0')
            ->withTransition('S1', '10', 'S0')
            ->withTransition('S1', '11', 'S1')
            ->build();
        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $pairs[] = '01';
        }
        return ['fsm' => $fsm, 'input' => $pairs];
    },
    function($data) {
        $data['fsm']->execute(new InputString($data['input']));
    }
);

// Benchmark 4: Service Layer
echo "\n4. SERVICE LAYER PERFORMANCE\n";
echo "-----------------------------\n";

$benchmark->run(
    'ModuloThreeService (no history)',
    function() {
        $service = new ModuloThreeService();
        $input = str_repeat('10110101', 125); // 1000 bits
        return ['service' => $service, 'input' => $input];
    },
    function($data) {
        $data['service']->calculate($data['input'], false);
    },
    100 // Fewer iterations for service layer
);

$benchmark->run(
    'ModuloThreeService (with history)',
    function() {
        $service = new ModuloThreeService();
        $input = str_repeat('10110101', 12); // 96 bits (smaller for history tracking)
        return ['service' => $service, 'input' => $input];
    },
    function($data) {
        $data['service']->calculate($data['input'], true);
    },
    100
);

// Benchmark 5: FSM Creation
echo "\n5. FSM CREATION PERFORMANCE\n";
echo "----------------------------\n";

$benchmark->run(
    'FSM Builder (small)',
    function() {
        return null;
    },
    function($data) {
        AutomatonBuilder::create()
            ->withStates('A', 'B')
            ->withAlphabet('0', '1')
            ->withInitialState('A')
            ->withFinalStates('B')
            ->withTransition('A', '0', 'A')
            ->withTransition('A', '1', 'B')
            ->withTransition('B', '0', 'B')
            ->withTransition('B', '1', 'A')
            ->build();
    },
    100
);

$benchmark->run(
    'FSM Builder (medium)',
    function() {
        return null;
    },
    function($data) {
        $builder = AutomatonBuilder::create()
            ->withStates('S0', 'S1', 'S2', 'S3', 'S4')
            ->withAlphabet('a', 'b', 'c')
            ->withInitialState('S0')
            ->withFinalStates('S4');
        
        // Add 15 transitions
        $states = ['S0', 'S1', 'S2', 'S3', 'S4'];
        $symbols = ['a', 'b', 'c'];
        foreach ($states as $from) {
            foreach ($symbols as $symbol) {
                $to = $states[array_rand($states)];
                $builder->withTransition($from, $symbol, $to);
            }
        }
        
        $builder->build();
    },
    100
);

// Benchmark 6: Application Layer
echo "\n6. APPLICATION LAYER\n";
echo "--------------------\n";

$benchmark->run(
    'FSMExecutor standard',
    function() {
        $fsm = ModuloThreeAutomaton::getInstance();
        $instance = new FSMInstance(
            'test-id',
            $fsm,
            new FSMMetadata('Test FSM')
        );
        $executor = new FSMExecutor();
        $input = str_repeat('10110101', 125);
        return [
            'executor' => $executor,
            'instance' => $instance,
            'input' => new InputString($input)
        ];
    },
    function($data) {
        $data['executor']->execute(
            $data['instance'],
            $data['input'],
            false
        );
    },
    100
);

$benchmark->run(
    'FSMExecutor compiled',
    function() {
        $fsm = ModuloThreeAutomaton::getInstance();
        $executor = new FSMExecutor();
        $input = str_repeat('10110101', 125);
        return [
            'executor' => $executor,
            'fsm' => $fsm,
            'input' => new InputString($input)
        ];
    },
    function($data) {
        $data['executor']->executeFast(
            $data['fsm'],
            $data['input']
        );
    },
    100
);

// Print summary
$benchmark->summary();

echo "\n========================================\n";
echo "   Benchmark Complete!\n";
echo "========================================\n\n";