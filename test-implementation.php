#!/usr/bin/env php
<?php

// Manual test without composer autoload
// This validates the core implementation logic

declare(strict_types=1);

// Manually include all required files
$srcPath = __DIR__ . '/src';

// Core Exception classes
require_once $srcPath . '/Core/Exception/InvalidAutomatonException.php';
require_once $srcPath . '/Core/Exception/InvalidInputException.php';
require_once $srcPath . '/Core/Exception/InvalidTransitionException.php';

// Value Objects
require_once $srcPath . '/Core/ValueObject/State.php';
require_once $srcPath . '/Core/ValueObject/Symbol.php';
require_once $srcPath . '/Core/ValueObject/StateSet.php';
require_once $srcPath . '/Core/ValueObject/Alphabet.php';
require_once $srcPath . '/Core/ValueObject/TransitionFunction.php';
require_once $srcPath . '/Core/ValueObject/InputString.php';

// Result classes
require_once $srcPath . '/Core/Result/TransitionRecord.php';
require_once $srcPath . '/Core/Result/ComputationResult.php';

// Core Model
require_once $srcPath . '/Core/Model/FiniteAutomaton.php';

// Builder
require_once $srcPath . '/Core/Builder/AutomatonBuilder.php';

// Performance
require_once $srcPath . '/Core/Performance/CompiledAutomaton.php';

// Examples
require_once $srcPath . '/Examples/ModuloThree/ModuloThreeResult.php';
require_once $srcPath . '/Examples/ModuloThree/ModuloThreeAutomaton.php';
require_once $srcPath . '/Examples/ModuloThree/ModuloThreeService.php';

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;
use FSM\Core\Performance\CompiledAutomaton;

echo "=================================================\n";
echo "FSM Library - Core Implementation Test\n";
echo "=================================================\n\n";

$passed = 0;
$failed = 0;

function test($description, $callback) {
    global $passed, $failed;
    echo "Testing: {$description}... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "✓ PASSED\n";
            $passed++;
        } else {
            echo "✗ FAILED\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ FAILED (Exception: {$e->getMessage()})\n";
        $failed++;
    }
}

// Test 1: Basic FSM Creation
test("Basic FSM creation with builder", function() {
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
    
    return $automaton !== null;
});

// Test 2: FSM Execution
test("FSM execution produces correct result", function() {
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
    
    $result = $automaton->execute(new InputString('01'));
    return (string)$result->finalState === 'B' && $result->isAccepted === true;
});

// Test 3: Modulo-3 Automaton
test("Modulo-3 calculation for binary '11' (decimal 3)", function() {
    $result = ModuloThreeAutomaton::calculate('11');
    return $result === 0;  // 3 mod 3 = 0
});

test("Modulo-3 calculation for binary '101' (decimal 5)", function() {
    $result = ModuloThreeAutomaton::calculate('101');
    return $result === 2;  // 5 mod 3 = 2
});

test("Modulo-3 calculation for binary '111' (decimal 7)", function() {
    $result = ModuloThreeAutomaton::calculate('111');
    return $result === 1;  // 7 mod 3 = 1
});

// Test 4: Transition Path
test("FSM records transition path correctly", function() {
    $automaton = ModuloThreeAutomaton::getInstance();
    $result = $automaton->execute(new InputString('10'));
    
    $transitions = $result->transitions;
    return count($transitions) === 2 &&
           (string)$transitions[0]->fromState === 'S0' &&
           (string)$transitions[0]->symbol === '1' &&
           (string)$transitions[0]->toState === 'S1' &&
           (string)$transitions[1]->fromState === 'S1' &&
           (string)$transitions[1]->symbol === '0' &&
           (string)$transitions[1]->toState === 'S2';
});

// Test 5: Compiled Automaton
test("Compiled automaton produces same results", function() {
    $automaton = ModuloThreeAutomaton::getInstance();
    $compiled = CompiledAutomaton::compile($automaton);
    
    // Test with binary '110' (decimal 6, mod 3 = 0)
    $input = '110';
    
    // Regular execution
    $regularResult = $automaton->execute(new InputString($input));
    $regularState = (string)$regularResult->finalState;
    
    // Compiled execution
    $stateIndex = $compiled->initialStateIndex;
    foreach (str_split($input) as $char) {
        $symbolIndex = $compiled->symbolIndices[$char];
        $stateIndex = $compiled->transitionTable[$stateIndex][$symbolIndex];
    }
    $compiledState = $compiled->states[$stateIndex];
    
    return $regularState === $compiledState && $compiledState === 'S0';
});

// Test 6: Invalid Input Handling
test("Invalid binary input throws exception", function() {
    try {
        ModuloThreeAutomaton::calculate('102');
        return false;
    } catch (InvalidArgumentException $e) {
        return true;
    }
});

// Test 7: Bulk Transitions
test("Bulk transitions with array notation", function() {
    $automaton = AutomatonBuilder::create()
        ->withStates('S0', 'S1', 'S2')
        ->withAlphabet('0', '1')
        ->withInitialState('S0')
        ->withFinalStates('S0', 'S1', 'S2')
        ->withTransitions([
            'S0:0' => 'S0',
            'S0:1' => 'S1',
            'S1:0' => 'S2',
            'S1:1' => 'S0',
            'S2:0' => 'S1',
            'S2:1' => 'S2',
        ])
        ->build();
    
    $result = $automaton->execute(new InputString('1011'));
    return (string)$result->finalState === 'S2';
});

// Test 8: ModuloThree Service
test("ModuloThree service with transitions", function() {
    $service = new FSM\Examples\ModuloThree\ModuloThreeService();
    $result = $service->calculate('101', true);
    
    return $result->modulo === 2 &&
           $result->finalState === 'S2' &&
           $result->decimalValue === '5' &&
           count($result->transitions) === 3;
});

echo "\n=================================================\n";
echo "Test Results:\n";
echo "  Passed: {$passed}\n";
echo "  Failed: {$failed}\n";
echo "  Total:  " . ($passed + $failed) . "\n";
echo "=================================================\n";

if ($failed === 0) {
    echo "\n✓ All tests passed! The FSM library is working correctly.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}