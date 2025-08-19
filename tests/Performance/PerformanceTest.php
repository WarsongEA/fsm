<?php

declare(strict_types=1);

namespace Tests\Performance;

use PHPUnit\Framework\TestCase;
use FSM\Core\Performance\CompiledAutomaton;
use FSM\Examples\ModuloThree\ModuloThreeAutomaton;
use FSM\Core\ValueObject\InputString;

final class PerformanceTest extends TestCase
{
    public function testCompiledAutomatonPerformance(): void
    {
        $automaton = ModuloThreeAutomaton::getInstance();
        $compiled = CompiledAutomaton::compile($automaton);
        
        $largeInput = str_repeat('10110101', 1000);
        
        $startTime = microtime(true);
        
        $stateIndex = $compiled->initialStateIndex;
        foreach (str_split($largeInput) as $char) {
            $symbolIndex = $compiled->symbolIndices[$char];
            $stateIndex = $compiled->transitionTable[$stateIndex][$symbolIndex];
        }
        
        $executionTime = microtime(true) - $startTime;
        
        $this->assertLessThan(0.1, $executionTime);
        
        $finalState = $compiled->states[$stateIndex];
        $this->assertContains($finalState, ['S0', 'S1', 'S2']);
    }
    
    public function testRegularAutomatonPerformance(): void
    {
        $automaton = ModuloThreeAutomaton::getInstance();
        $largeInput = str_repeat('10110101', 100);
        
        $startTime = microtime(true);
        $result = $automaton->execute(new InputString($largeInput));
        $executionTime = microtime(true) - $startTime;
        
        $this->assertLessThan(0.5, $executionTime);
        $this->assertContains((string)$result->finalState, ['S0', 'S1', 'S2']);
    }
    
    public function testMemoryUsage(): void
    {
        $automaton = ModuloThreeAutomaton::getInstance();
        $compiled = CompiledAutomaton::compile($automaton);
        
        $memoryBefore = memory_get_usage(true);
        
        for ($i = 0; $i < 1000; $i++) {
            $input = decbin($i);
            $automaton->execute(new InputString($input));
        }
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;
        
        $this->assertLessThan(10, $memoryUsed);
    }
}