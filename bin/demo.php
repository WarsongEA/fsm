#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FSM\Application\Command\CreateFSMCommand;
use FSM\Application\Command\ExecuteFSMCommand;
use FSM\Application\Handler\CreateFSMHandler;
use FSM\Application\Handler\ExecuteFSMHandler;
use FSM\Application\Service\FSMExecutor;
use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use FSM\Examples\ModuloThree\ModuloThreeService;
use FSM\Examples\Regex\EndsWithZeroOneAutomaton;
use FSM\Infrastructure\Persistence\InMemoryFSMRepository;
use FSM\Infrastructure\Event\NullEventDispatcher;

echo "\n";
echo "========================================\n";
echo "   FSM Library Demo - PHP Implementation\n";
echo "========================================\n\n";

// Demo 1: Modulo-3 FSM
echo "1. MODULO-3 FSM DEMO\n";
echo "--------------------\n";

$service = new ModuloThreeService();

$testCases = [
    '110' => 6,    // Binary 110 = 6 decimal
    '1001' => 9,   // Binary 1001 = 9 decimal
    '1111' => 15,  // Binary 1111 = 15 decimal
    '10101' => 21, // Binary 10101 = 21 decimal
];

foreach ($testCases as $binary => $decimal) {
    $result = $service->calculate($binary, true);
    echo "  Binary: {$binary} (Decimal: {$decimal})\n";
    echo "  Result: {$decimal} mod 3 = {$result->modulo}\n";
    echo "  Final State: {$result->finalState}\n";
    echo "  Execution Time: " . number_format($result->executionTimeMs, 3) . " ms\n";
    
    // Show transition path
    echo "  Path: ";
    $path = [];
    foreach ($result->transitions as $t) {
        $path[] = "{$t['from']}--{$t['input']}-->{$t['to']}";
    }
    echo implode(' ', $path) . "\n\n";
}

// Demo 2: Pattern Matching FSM
echo "2. PATTERN MATCHING FSM DEMO\n";
echo "-----------------------------\n";
echo "Pattern: Binary strings ending with '01'\n\n";

$testStrings = [
    '01' => true,
    '101' => true,
    '1101' => true,
    '10' => false,
    '110' => false,
    '011' => false,
];

foreach ($testStrings as $input => $expected) {
    $result = EndsWithZeroOneAutomaton::matches($input);
    $status = $result ? '✓ Accepted' : '✗ Rejected';
    $expectation = $expected ? 'Should accept' : 'Should reject';
    echo "  Input: '{$input}' => {$status} ({$expectation})\n";
}

// Demo 3: FSM Builder API
echo "\n3. FSM BUILDER API DEMO\n";
echo "------------------------\n";
echo "Creating a custom even/odd detector FSM...\n\n";

$customFSM = AutomatonBuilder::create()
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

$testInputs = ['0', '1', '11', '111', '1010', '10101'];

foreach ($testInputs as $input) {
    $result = $customFSM->execute(new InputString($input));
    $ones = substr_count($input, '1');
    $status = $result->isAccepted ? 'Even' : 'Odd';
    echo "  Input: '{$input}' has {$ones} ones => {$status} number of 1s\n";
}

// Demo 4: Application Layer with Handlers
echo "\n4. APPLICATION LAYER DEMO\n";
echo "--------------------------\n";
echo "Using command/handler pattern...\n\n";

$repository = new InMemoryFSMRepository();
$eventDispatcher = new NullEventDispatcher();
$createHandler = new CreateFSMHandler($repository, $eventDispatcher);
$executeHandler = new ExecuteFSMHandler($repository, new FSMExecutor());

// Create an FSM through the application layer
$createCommand = new CreateFSMCommand(
    states: ['Red', 'Yellow', 'Green'],
    alphabet: ['tick'],
    initialState: 'Red',
    finalStates: ['Red', 'Yellow', 'Green'],
    transitions: [
        'Red:tick' => 'Green',
        'Green:tick' => 'Yellow',
        'Yellow:tick' => 'Red',
    ],
    name: 'Traffic Light FSM',
    description: 'Simple traffic light state machine'
);

$createResult = $createHandler->handle($createCommand);
echo "  Created FSM: {$createResult->metadata->name}\n";
echo "  FSM ID: {$createResult->fsmId}\n\n";

// Execute the FSM
$ticks = ['tick', 'tick', 'tick', 'tick'];
$currentState = 'Red';

echo "  Traffic Light Sequence:\n";
echo "  Initial: {$currentState}\n";

foreach ($ticks as $i => $tick) {
    $executeCommand = new ExecuteFSMCommand(
        fsmId: $createResult->fsmId,
        input: $tick,
        recordHistory: true
    );
    
    $executeResult = $executeHandler->handle($executeCommand);
    echo "  After tick " . ($i + 1) . ": {$executeResult->finalState}\n";
}

// Demo 5: Performance Test
echo "\n5. PERFORMANCE TEST\n";
echo "--------------------\n";

$largeBinary = str_repeat('10110101', 1000); // 8,000 bits
echo "  Testing with " . strlen($largeBinary) . "-bit binary number...\n";

$startTime = microtime(true);
$result = $service->calculate($largeBinary, false);
$totalTime = (microtime(true) - $startTime) * 1000;

echo "  Result: Large number mod 3 = {$result->modulo}\n";
echo "  Processing time: " . number_format($totalTime, 3) . " ms\n";
echo "  Throughput: " . number_format(strlen($largeBinary) / ($totalTime / 1000), 0) . " bits/second\n";

echo "\n========================================\n";
echo "   Demo Complete!\n";
echo "========================================\n\n";