<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use FSM\Core\Builder\AutomatonBuilder;
use FSM\Core\ValueObject\InputString;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;

echo "FSM Library - Basic Usage Examples\n";
echo "===================================\n\n";

// Example 1: Create a simple even/odd detector
echo "1. Even/Odd Detector FSM\n";
echo "-------------------------\n";

$evenOddDetector = AutomatonBuilder::create()
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

$testInputs = ['', '1', '11', '101', '1010', '11111'];

foreach ($testInputs as $input) {
    $result = $evenOddDetector->execute(new InputString($input));
    $status = $result->isAccepted ? 'Even' : 'Odd';
    echo "Input: '{$input}' -> {$status} number of 1s (State: {$result->finalState})\n";
}

echo "\n";

// Example 2: Binary string ending with "01"
echo "2. Pattern Matcher FSM (ends with '01')\n";
echo "----------------------------------------\n";

$patternMatcher = AutomatonBuilder::create()
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

$testPatterns = ['01', '101', '001', '1101', '0101', '111'];

foreach ($testPatterns as $pattern) {
    $result = $patternMatcher->execute(new InputString($pattern));
    $matches = $result->isAccepted ? 'MATCHES' : 'does not match';
    echo "'{$pattern}' {$matches} pattern (ends with '01')\n";
}

echo "\n";

// Example 3: Modulo-3 Calculator
echo "3. Modulo-3 Calculator FSM\n";
echo "--------------------------\n";

$moduloTestCases = [
    '0' => 0,
    '1' => 1,
    '10' => 2,
    '11' => 3,
    '100' => 4,
    '101' => 5,
    '110' => 6,
    '111' => 7,
    '1000' => 8,
    '1001' => 9,
];

foreach ($moduloTestCases as $binary => $decimal) {
    $modulo = ModuloThreeAutomaton::calculate($binary);
    echo "Binary: {$binary} (Decimal: {$decimal}) mod 3 = {$modulo}\n";
}

echo "\n";

// Example 4: Show transition path
echo "4. Transition Path Visualization\n";
echo "---------------------------------\n";

$automaton = ModuloThreeAutomaton::getInstance();
$input = '1011';
$result = $automaton->execute(new InputString($input));

echo "Input: '{$input}'\n";
echo "Transitions:\n";

foreach ($result->transitions as $transition) {
    echo "  {$transition->fromState} --[{$transition->symbol}]--> {$transition->toState}\n";
}

echo "Final State: {$result->finalState}\n";
echo "Result: " . ModuloThreeAutomaton::calculate($input) . " (mod 3)\n";

echo "\n";

// Example 5: Performance comparison
echo "5. Performance Comparison\n";
echo "-------------------------\n";

use FSM\Core\Performance\CompiledAutomaton;

$regularAutomaton = ModuloThreeAutomaton::getInstance();
$compiledAutomaton = CompiledAutomaton::compile($regularAutomaton);

$testInput = str_repeat('10110101', 100);

// Regular execution
$startTime = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $regularAutomaton->execute(new InputString($testInput));
}
$regularTime = (microtime(true) - $startTime) * 1000;

// Compiled execution
$startTime = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $stateIndex = $compiledAutomaton->initialStateIndex;
    foreach (str_split($testInput) as $char) {
        $symbolIndex = $compiledAutomaton->symbolIndices[$char];
        $stateIndex = $compiledAutomaton->transitionTable[$stateIndex][$symbolIndex];
    }
}
$compiledTime = (microtime(true) - $startTime) * 1000;

echo "Regular FSM: {$regularTime}ms for 100 iterations\n";
echo "Compiled FSM: {$compiledTime}ms for 100 iterations\n";
$speedup = $regularTime / $compiledTime;
echo "Speedup: {$speedup}x faster\n";

echo "\nDone!\n";