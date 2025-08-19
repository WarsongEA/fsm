#!/usr/bin/env php
<?php

declare(strict_types=1);

// Simple test runner without PHPUnit dependency
require_once __DIR__ . '/../vendor/autoload.php';

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeService;
use FSM\Examples\Regex\EndsWithZeroOneAutomaton;
use FSM\Application\Command\CreateFSMCommand;
use FSM\Application\Handler\CreateFSMHandler;
use FSM\Application\Handler\ExecuteFSMHandler;
use FSM\Application\Command\ExecuteFSMCommand;
use FSM\Application\Service\FSMExecutor;
use FSM\Infrastructure\Persistence\InMemoryFSMRepository;
use FSM\Infrastructure\Event\NullEventDispatcher;

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function test(string $name, callable $testFunc): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    try {
        $testFunc();
        echo "✓ {$name}\n";
        $passedTests++;
    } catch (Exception $e) {
        echo "✗ {$name}: {$e->getMessage()}\n";
        $failedTests++;
    }
}

function assertEquals($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $msg = $message ?: "Expected " . json_encode($expected) . " but got " . json_encode($actual);
        throw new Exception($msg);
    }
}

function assertTrue($value, string $message = ''): void
{
    if ($value !== true) {
        throw new Exception($message ?: "Expected true but got " . json_encode($value));
    }
}

function assertFalse($value, string $message = ''): void
{
    if ($value !== false) {
        throw new Exception($message ?: "Expected false but got " . json_encode($value));
    }
}

echo "\n";
echo "========================================\n";
echo "   FSM Library Test Suite\n";
echo "========================================\n\n";

// Test 1: Basic FSM Creation
echo "Core Tests:\n";
echo "-----------\n";

test('Create simple FSM with builder', function() {
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
    
    $result = $fsm->execute(new InputString('01'));
    assertEquals('B', (string)$result->finalState);
    assertTrue($result->isAccepted);
});

test('FSM rejects invalid input', function() {
    $fsm = AutomatonBuilder::create()
        ->withStates('A')
        ->withAlphabet('0', '1')
        ->withInitialState('A')
        ->withFinalStates('A')
        ->withTransition('A', '0', 'A')
        ->withTransition('A', '1', 'A')
        ->build();
    
    try {
        $fsm->execute(new InputString('2'));
        throw new Exception('Should have thrown exception for invalid input');
    } catch (\FSM\Core\Exception\InvalidInputException $e) {
        // Expected
    }
});

// Test 2: Modulo-3 FSM
echo "\nModulo-3 Tests:\n";
echo "---------------\n";

test('Modulo-3 for small numbers', function() {
    assertEquals(0, ModuloThreeAutomaton::calculate('0'));    // 0 mod 3 = 0
    assertEquals(1, ModuloThreeAutomaton::calculate('1'));    // 1 mod 3 = 1
    assertEquals(2, ModuloThreeAutomaton::calculate('10'));   // 2 mod 3 = 2
    assertEquals(0, ModuloThreeAutomaton::calculate('11'));   // 3 mod 3 = 0
    assertEquals(1, ModuloThreeAutomaton::calculate('100'));  // 4 mod 3 = 1
    assertEquals(2, ModuloThreeAutomaton::calculate('101'));  // 5 mod 3 = 2
    assertEquals(0, ModuloThreeAutomaton::calculate('110'));  // 6 mod 3 = 0
});

test('Modulo-3 for larger numbers', function() {
    assertEquals(0, ModuloThreeAutomaton::calculate('1111'));   // 15 mod 3 = 0
    assertEquals(0, ModuloThreeAutomaton::calculate('10101'));  // 21 mod 3 = 0
    assertEquals(0, ModuloThreeAutomaton::calculate('11011'));  // 27 mod 3 = 0
    assertEquals(2, ModuloThreeAutomaton::calculate('100001')); // 33 mod 3 = 0
});

test('Modulo-3 service with history', function() {
    $service = new ModuloThreeService();
    $result = $service->calculate('110', true);
    
    assertEquals(0, $result->modulo);
    assertEquals('S0', $result->finalState);
    assertEquals('6', $result->decimalValue);
    assertEquals(3, count($result->transitions));
});

// Test 3: Pattern Matching
echo "\nPattern Matching Tests:\n";
echo "-----------------------\n";

test('Ends with 01 pattern - accepts', function() {
    assertTrue(EndsWithZeroOneAutomaton::matches('01'));
    assertTrue(EndsWithZeroOneAutomaton::matches('101'));
    assertTrue(EndsWithZeroOneAutomaton::matches('0101'));
    assertTrue(EndsWithZeroOneAutomaton::matches('1101'));
});

test('Ends with 01 pattern - rejects', function() {
    assertFalse(EndsWithZeroOneAutomaton::matches('10'));
    assertFalse(EndsWithZeroOneAutomaton::matches('00'));
    assertFalse(EndsWithZeroOneAutomaton::matches('11'));
    assertFalse(EndsWithZeroOneAutomaton::matches('010'));
});

// Test 4: Application Layer
echo "\nApplication Layer Tests:\n";
echo "------------------------\n";

test('Create FSM through handler', function() {
    $repository = new InMemoryFSMRepository();
    $handler = new CreateFSMHandler($repository, new NullEventDispatcher());
    
    $command = new CreateFSMCommand(
        states: ['A', 'B'],
        alphabet: ['0', '1'],
        initialState: 'A',
        finalStates: ['B'],
        transitions: [
            'A:0' => 'A',
            'A:1' => 'B',
            'B:0' => 'B',
            'B:1' => 'A',
        ],
        name: 'Test FSM'
    );
    
    $result = $handler->handle($command);
    assertTrue(!empty($result->fsmId));
    assertEquals('Test FSM', $result->metadata->name);
});

test('Execute FSM through handler', function() {
    $repository = new InMemoryFSMRepository();
    $createHandler = new CreateFSMHandler($repository, new NullEventDispatcher());
    $executeHandler = new ExecuteFSMHandler($repository, new FSMExecutor());
    
    // Create FSM
    $createCommand = new CreateFSMCommand(
        states: ['Even', 'Odd'],
        alphabet: ['0', '1'],
        initialState: 'Even',
        finalStates: ['Even'],
        transitions: [
            'Even:0' => 'Even',
            'Even:1' => 'Odd',
            'Odd:0' => 'Odd',
            'Odd:1' => 'Even',
        ]
    );
    
    $createResult = $createHandler->handle($createCommand);
    
    // Execute FSM
    $executeCommand = new ExecuteFSMCommand(
        fsmId: $createResult->fsmId,
        input: '11',  // Two 1s = even
        recordHistory: false
    );
    
    $executeResult = $executeHandler->handle($executeCommand);
    assertEquals('Even', $executeResult->finalState);
    assertTrue($executeResult->isAccepted);
});

// Test 5: Multi-character symbols
echo "\nAdvanced Features Tests:\n";
echo "------------------------\n";

test('FSM with multi-character symbols', function() {
    $fsm = AutomatonBuilder::create()
        ->withStates('Start', 'End')
        ->withAlphabet('AB', 'CD')
        ->withInitialState('Start')
        ->withFinalStates('End')
        ->withTransition('Start', 'AB', 'End')
        ->withTransition('Start', 'CD', 'Start')
        ->withTransition('End', 'AB', 'End')
        ->withTransition('End', 'CD', 'Start')
        ->build();
    
    $result = $fsm->execute(new InputString(['CD', 'CD', 'AB']));
    assertEquals('End', (string)$result->finalState);
    assertTrue($result->isAccepted);
});

// Summary
echo "\n========================================\n";
echo "   Test Results\n";
echo "========================================\n";
echo "Total: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n";

if ($failedTests === 0) {
    echo "\n✓ All tests passed!\n\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed!\n\n";
    exit(1);
}